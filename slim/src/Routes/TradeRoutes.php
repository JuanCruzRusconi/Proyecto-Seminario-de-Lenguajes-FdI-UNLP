<?php

use Slim\App;
use App\Middlewares\AuthMiddleware;
use App\Controllers\TradeController;

return function(App $app) {
    
    $app->post('/trade/buy', [TradeController::class, 'postBuy'])
        ->add(new AuthMiddleware);

    $app->post('/trade/sell', [TradeController::class, 'postSell'])
        ->add(new AuthMiddleware);

};