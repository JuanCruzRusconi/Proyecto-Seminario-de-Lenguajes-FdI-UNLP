<?php

use Slim\App;
use App\Middlewares\AuthMiddleware;
use App\Controllers\AssetController;

return function(App $app) {

    $app->get('/assets', [AssetController::class, 'getAssets']);

    $app->put('/assets', [AssetController::class, 'putAssets'])
        ->add(new AuthMiddleware());

    $app->get('/assets/{asset_id}/history/{quantity}', [AssetController::class, 'getAssetHistory']);

};