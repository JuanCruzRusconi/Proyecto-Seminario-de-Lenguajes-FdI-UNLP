<?php

namespace App\Controllers;
use App\Config\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AuthController {

    // LOGIN JWT
    public function postLoginJwt(Request $request, Response $response) {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // VALIDAR EMAIL
        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(["error" => "Email inválido."]));
            return $response->withStatus(400);
        }

        // VALIDAR CONTRASEÑA
        if(empty($password)) {
            $response->getBody()->write(json_encode(["error" => "La contraseña es obligatoria"]));
            return $response->withStatus(400);
        }

        // CONEXIÓN
        $pdo = Database::PDO();

        // BUSCAR USUARIO
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // VERIFICACIÓN DE EXISTENCIA
        if(!$user) {
            $response->getBody()->write(json_encode(["error" => "Las credenciales son inválidas."]));
            return $response->withStatus(401);
        }

        // VERIFICAR PASSWORD
        if(!password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(["error" => "Las credenciales son inválidas."]));
            return $response->withStatus(401);
        }

        // USUARIO LOGUEADO
        // GENERAR PAYLOAD JWT
        $payload = [
            "iat" => time(),
            "exp" => time() + 300,
            "data" => [
                "id" => $user['id'],
                "email" => $user['email']
            ]
        ];
        // GENERAR TOKEN JWT
        $jwt = JWT::encode($payload, SECRET_KEY, 'HS256');

        $response->getBody()->write(json_encode([
            "mensaje" => "Usuario logueado", 
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email']
            ],
            "token" => $jwt
        ]));

        return $response;

    }

    // LOGIN SESION
    public function postLogin(Request $request, Response $response) {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // VALIDAR INPUTS
        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
            $response->getBody()->write(json_encode(["error" => "Campos incorrectos."]));
            return $response->withStatus(401);
        }

        // VALIDAR DB CREDENCIALES
        $pdo = Database::PDO();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => "Credenciales inválidas."]));
            return $response->withStatus(401);
        }

        // GENERAR TOKEN SESSION
        $token = bin2hex(random_bytes(32));

        $exp = dat('Y-m-d H:i:s', time() + 300);

        $stmt = $pdo->prepare("UPDATE users SET token = ?, token_expiration_at = ? WHERE id = ?");
        $stmt->execute([$token, $exp, $user['id']]);

        $response->getBody()->write(json_encode([
            "mensaje" => "Usuario logueado.",
            "usuario" => [
                "id" => $user['id'],
                "nombre" => $user['name'],
                "email" => $user['email']
            ],
            "token" => $token,
            "expira" => $exp
        ]));

        return $response;

    }
}