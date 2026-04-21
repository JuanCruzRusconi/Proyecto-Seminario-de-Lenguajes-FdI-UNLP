<?php

use Slim\App;
use App\Middlewares\AuthMiddleware;
use App\Controllers\UserController;

return function(App $app) {

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
    $app->put('/users/{id}', [UserController::class, 'putUsersId'])
        ->add(new AuthMiddleware());

};