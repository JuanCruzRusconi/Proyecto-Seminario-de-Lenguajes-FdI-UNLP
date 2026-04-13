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



$app->run();
