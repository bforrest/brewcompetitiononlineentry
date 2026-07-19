<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use Slim\App;

/**
 * Builds the Slim app. Does NOT run it - callers (index.php, tests) decide
 * whether to ->run() against real superglobals or ->handle() a constructed
 * PSR-7 request.
 */
function buildApp(): App
{
    $container = require __DIR__ . '/container.php';
    $app = Bridge::create($container);

    $app->get('/__kernel_hello', function ($request, $response) {
        $response->getBody()->write('ok');
        return $response;
    });

    return $app;
}
