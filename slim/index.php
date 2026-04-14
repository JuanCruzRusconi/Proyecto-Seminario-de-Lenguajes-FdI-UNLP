<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// DOTENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->safeLoad();

define('SECRET_KEY', $_ENV['JWT_SECRET']);

$app = AppFactory::create();

// PARSEO DEL BODY
$app->addBodyParsingMiddleware();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

//MIDDLEWARES

$authMiddleware = function($request, $handler) {
    
    $authHeader = $request->getHeaderLine('Authorization');

    if(!$authHeader) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(["error" => "Se requiere el token para la petición."]));
        return $response->withStatus(401);
    }

    $token = trim(str_replace('Bearer', '', $authHeader));

    try {
        $decoded = JWT::decode($token, new Key(SECRET_KEY, 'HS256'));
        $request = $request->withAttribute('user', $decoded->data);
    } catch (Exception $e) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(["error" => "El token es inválido"]));
        return $response->withStatus(401);
    }

    return $handler->handle($request);
};

// ENDPOINTS

// // TEST
$app->get('/test', function ($request, $response) {
    $response->getBody()->write(json_encode(["mensaje" => "Funciona correctamente."]));
    return $response;
});

// USUARIOS

// GET PROFILE LOGUEADO
$app->get('/profile', function($request, $response) {

    $user = $request->getAttribute('user');

    if(!$user) {
        $response->getBody()->write(json_encode(["error" => "No se encunetra ningún usuario logueado."]));
        return $response->withStatus(401);
    }

    $response->getBody()->write(json_encode([
        "mensaje" => "Perfil de usuario logueado:",
        "user" => [
            $user
        ]    
    ]));

    return $response;

})->add($authMiddleware);

// GET USERS
$app->get('/users', function ($request, $response) {

    $user = $request->getAttribute('user');
    // CONEXIÓN
    $pdo = new PDO("mysql:host=db;dbname=seminariophp", "root", "root");

    // QUERY
    $stmt = $pdo->query("SELECT id, name, balance FROM users");

    // FETCH
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // RESPUESTA
    $response->getBody()->write(json_encode($users));
    return $response;

})->add($authMiddleware);

// GET USER ID
$app->get('/users/{id}', function($request, $response, $args) {

    // OBTENER ID
    $id = $args['id'];

    // VALIDACIÓN DE ID;
    if(!is_numeric($id)) {
        $response->getBody()->write(json_encode(["error" => "ID inválido."]));
        return $response->withStatus(400);
    }

    $pdo = new PDO("mysql:host=db;dbname=seminariophp" , "root", "root");

    $stmt = $pdo->prepare("SELECT id, name, email, balance FROM users WHERE id = ?");
    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // NO EXISTE
    if(!$user) {
        $response->getBody()->write(json_encode(["error" => "Usuario no encontrado."]));
        return $response->withStatus(400);
    }

    // USUARIO
    $response->getBody()->write(json_encode($user));
    return $response;
});

// POST USERS
$app->post('/users', function($request, $response) {
    $data = $request->getParsedBody();

    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // VALIDACION NOMBRE
    if(empty($name) || !preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $response->getBody()->write(json_encode(["error" => "Nombre incompleto."]));
        return $response->withStatus(400);
    }

    // VALIDACION EMAIL
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode(["error" => "Correo no cumple condiciones."]));
        return $response->withStatus(400);
    }

    // VALIDACION CONTRASEÑA
    // CONTRASEÑ VACÍA
    if(empty($password)) {
        $response->getBody()->write(json_encode(["error" => "Debe ingresar una contraseña válida."]));
        return $response->withStatus(400);
    }
    // CONTRASEÑA DÉBIL
    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $response->getBody()->write(json_encode(["error" => "La contraseña es demasiado débil."]));
        return $response->withStatus(400);
    }

    // HASH CONTRASEÑA
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    //CONEXIÓN a la DB
    $pdo = new PDO("mysql:host=db;dbname=seminariophp", "root", "root");

    // VERIFICAR EXISTENCIA DE EMAIL
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    $user = $stmt->fetch();
    if($user) {
        $response->getBody()->write(json_encode(["error" => "El correo ya se encuentra registrado."]));
        return $response->withStatus(400);
    }

    // INSERT
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword]);

    $response->getBody()->write(json_encode(["mensaje" => "Usuario registrado correctamente"]));
    return $response;
});

// PUT USER ID
$app->put('/users/{id}', function($request, $response, $args) {
    // OBTENER ID DEL HEADER    
    $id = $args['id'];

    $user = $request->getAttribute('user');
    $userId = (int) $user->id;

    $data = $request->getParsedBody();

    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    // QUERY DINÁMICA
    $fields = [];
    $params = [];

    // VALIDAR ID TIPO INT
    if(!is_numeric($id)) {
        $response->getBody()->write(json_encode(["error" => "ID inválido."]));
        return $response->withStatus(400);
    }

    // VALIDACION DEL ID CON JWT
    if((int)$id !== $userId) {
        $response->getBody()->write(json_encode(["error" => "Debe ser el usuario logueado para hacer la actualización."]));
        return $response->withStatus(403);
    }

    // VALIDACIÓN DE CAMPOS
    if($name !== null) {
        if(empty($name) || !preg_match("/^[a-zA-Z\s]+$/", $name)) {
            $response->getBody()->write(json_encode(["error" => "Nombre inválido."]));
            return $response->withStatus(400);
        }
        $fields[] = "name = ?";
        $params[] = $name;
    }
    
    if($email !== null) {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(["error" => "Email inválido."]));
            return $response->withStatus(400);
        }
        $fields[] = "email = ?";
        $params[] = $email;
    }

    if($password !== null) {
        if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            $response->getBody()->write(json_encode(["error" => "Contraseña inválida."]));
            return $response->withStatus(400);
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $fields[] = "password = ?";
        $params[] = $hashedPassword;
    }

    if(empty($fields)) {
        $response->getBody()->write(json_encode(["error" => "No se ingresaron campos para actualizar."]));
        return $response->withStatus(400);
    }

    // CONEXIÓN CON LA BD
    $pdo = new PDO("mysql:host=db;dbname=seminariophp", "root", "root");

    // VERIFICAR EXISTENCIA DE USUARIO
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);

    if(!$stmt->fetch()) {
        $response->getBody()->write(json_encode(["error" => "Usuario no encontrado."]));
        return $response->withStatus(404);
    }

    // ACTUALIZAR
    $sql = "UPDATE users SET " .implode(", ", $fields) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $response->getBody()->write(json_encode(["mensaje" => "Usuario actualizado correctamente."]));
    return $response;
    // $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
    // $stmt->execute([$name, $email, $hashedPassword, $id]);
    
    // $response->getBody()->write(json_encode(["mensaje" => "Usuario actualizado correctamente."]));
    // return $response;

})->add($authMiddleware);