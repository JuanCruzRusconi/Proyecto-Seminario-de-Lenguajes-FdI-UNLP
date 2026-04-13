<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

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

// TEST
$app->get('/test', function ($request, $response) {
    $data = ["mensaje" => "funciona perfecto"];
    
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// USUARIOS

// GET USERS
$app->get('/users', function ($request, $response) {
    // CONEXIÓN
    $pdo = new PDO("mysql:host=db;dbname=seminariophp", "root", "root");

    // QUERY
    $stmt = $pdo->query("SELECT id, name, balance FROM users");

    // FETCH
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // RESPUESTA
    $response->getBody()->write(json_encode($users));
    return $response;
});

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


$app->run();
