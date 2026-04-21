<?php

namespace App\Controllers;
use PDO;
use App\Config\Database;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class UserController {

    public function getTest(Request $request, Response $response) {
        $response->getBody()->write(json_encode(["mensaje" => "Funciona correctamente."]));
        return $response;
    }

    public function getProfile(Request $request, Response $response) {
        
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

    }

    // GET USERS
    public function getUsers(Request $request, Response $response) {

        $user = $request->getAttribute('user');
        // CONEXIÓN
        $pdo = Database::PDO();

        // QUERY
        $stmt = $pdo->query("SELECT id, name, balance FROM users");

        // FETCH
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // RESPUESTA
        $response->getBody()->write(json_encode($users));
        return $response;

    }

    // GET USER ID
    public function getUsersId(Request $request, Response $response, $args) {

        // OBTENER ID
        $id = $args['id'];

        // VALIDACIÓN DE ID;
        if(!is_numeric($id)) {
            $response->getBody()->write(json_encode(["error" => "ID inválido."]));
            return $response->withStatus(400);
        }
        //$pdo = new PDO("sql:host=db;dbname=seminariophp", "root", "root");
        $pdo = Database::PDO();

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
    }

    // POST USERS
    public function postUsers(Request $request, Response $response) {
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
        $pdo = Database::PDO();

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
    }

    // PUT USER ID
    public function putUsersId(Request $request, Response $response, $args) {
        // OBTENER ID DEL HEADER    
        $id = $args['id'];

        $user = $request->getAttribute('user');
        $userId = (int) $user['id'];

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
        $pdo = Database::PDO();

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

    }
}