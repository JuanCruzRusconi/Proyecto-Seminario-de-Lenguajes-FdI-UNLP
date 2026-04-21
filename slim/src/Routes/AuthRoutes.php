<?php

use Slim\App;
use App\Middlewares\AuthMiddleware;
use App\Controllers\AuthController;

return function(App $app) {

    // LOGIN
    $app->post('/login', [AuthController::class, 'postLogin']);

    // LOGOUT
    $app->post('/logout', [AuthController::class, 'postLogout'])
        ->add(new AuthMiddleware());

    // AUTENTICACION
    // JWT
    // $app->post('/loginjwt', [AuthController::class, 'postLoginJwt']);

    // // LOGIN SESSION
    // $app->post('/login', [AuthController::class, 'postLogin']);
    
    // // LOGOUT SESSION
    // $app->post('/logout', [AuthController::class, 'postLogout'])
    //     ->add(new AuthMiddleware());

};