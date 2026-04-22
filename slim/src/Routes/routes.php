<?php

use Slim\App;

// JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

return function(App $app) {

    // TEST
    $app->get('/test', function($request, $response) {
        $response->getBody()->write(json_encode(["mensaje" => "Funciona correctamente."]));
        return $response->withStatus(200);
    });

    // RUTAS AUTENTICACION
    (require __DIR__ . '/AuthRoutes.php')($app);

    // RUTAS USUARIOS
    (require __DIR__ . '/UserRoutes.php')($app);

    (require __DIR__ . '/AssetRoutes.php')($app);
    

};