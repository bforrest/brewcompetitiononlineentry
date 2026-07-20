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

    // Middleware add() order is LIFO for the request phase (last add() =
    // outermost = runs first). Desired execution order: Session ->
    // Authentication -> Slim's own routing -> Authorization -> route
    // handler. AuthorizationMiddleware reads the MATCHED route's name (set
    // via ->setName() on every route below) to determine which policy
    // entry governs, so Slim's routing must already have run by the time
    // it executes - hence addRoutingMiddleware() sits between the add()
    // calls for Authorization and Authentication here (Task 8a fix).
    $app->add(new \Bcoem\Kernel\Middleware\AuthorizationMiddleware(
        \Bcoem\Security\AccessPolicy::fromFile(__DIR__ . '/../../config/access_policy.php')
    ));
    $app->addRoutingMiddleware();
    $app->add(new \Bcoem\Kernel\Middleware\AuthenticationMiddleware());
    $app->add(new \Bcoem\Kernel\Middleware\SessionMiddleware());

    $app->get('/__kernel_hello', function ($request, $response) {
        $response->getBody()->write('ok');
        return $response;
    })->setName('section');

    // Register one route per side door, derived directly from
    // config/access_policy.php's file:* keys - that map is the single
    // source of truth for exactly which side doors exist (Task 3/3a's
    // whole point), so the route list is derived from it rather than
    // hand-maintained here, where it could silently drift out of sync.
    $policyMap = require __DIR__ . '/../../config/access_policy.php';
    $fileRoutes = [];
    foreach (array_keys($policyMap) as $key) {
        if (str_starts_with($key, 'file:')) {
            $fileRoutes[] = substr($key, strlen('file:'));
        }
    }

    foreach ($fileRoutes as $file) {
        $webPath = '/' . $file;
        $app->map(['GET', 'POST'], $webPath, new \Bcoem\Legacy\LegacyFileHandler($file))
            ->setName('file:' . $file);
    }

    return $app;
}
