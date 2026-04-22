<?php

use Slim\App;
use App\Controllers\AssetController;

return function(App $app) {

    $app->get('/assets', [AssetController::class, 'getAssets']);

};