<?php

use Slim\App;
use App\Middlewares\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;

// JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

return function(App $app) {

    // TEST
    $app->get('/test', [UserController::class, 'getTest']);

    // AUTENTICACION
    // JWT
    $app->post('/loginjwt', [AuthController::class, 'postLoginJwt']);

    //SESION
    $app->post('/login', [AuthController::class, 'postLogin']);
    
    // USUARIOS

    // GET PROFILE LOGUEADO
    $app->get('/profile', [UserController::class, 'getProfile'])
        ->add(new AuthMiddleware());

    // GET USERS
    $app->get('/users', [UserController::class, 'getUsers'])
        ->add(new AuthMiddleware());

    // GET USER ID
    $app->get('/users/{id}', [UserController::class, 'getUsersId']);

    // POST USERS
    $app->post('/users', [UserController::class, 'postUsers']);

    // PUT USER ID
    $app->put('/users/{id}', [UserController::class, 'putUser'])
        ->add(new AuthMiddleware());

};