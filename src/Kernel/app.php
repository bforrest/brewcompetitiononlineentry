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

    $app->add(new \Bcoem\Kernel\Middleware\AuthorizationMiddleware(
        \Bcoem\Security\AccessPolicy::fromFile(__DIR__ . '/../../config/access_policy.php')
    ));
    $app->add(new \Bcoem\Kernel\Middleware\AuthenticationMiddleware());
    $app->add(new \Bcoem\Kernel\Middleware\SessionMiddleware());

    $app->get('/__kernel_hello', function ($request, $response) {
        $response->getBody()->write('ok');
        return $response;
    });

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
        $routeAttr = function ($request, $handler) use ($file) {
            return $handler->handle(
                $request->withAttribute('routeType', 'file')->withAttribute('routeFile', $file)
            );
        };
        $app->map(['GET', 'POST'], $webPath, new \Bcoem\Legacy\LegacyFileHandler($file))
            ->add($routeAttr);
    }

    return $app;
}
