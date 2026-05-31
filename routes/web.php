<?php
declare(strict_types=1);

use App\Controllers\UserController;
use Flint\Response;

$router->get('/', fn() => Response::json(['framework' => 'Flint', 'status' => 'ok']));

$router->group(['prefix' => '/api'], function ($router) {
    $router->get('/users',         [UserController::class, 'index']);
    $router->post('/users',        [UserController::class, 'store']);
    $router->get('/users/{id}',    [UserController::class, 'show']);
    $router->delete('/users/{id}', [UserController::class, 'destroy']);
});
