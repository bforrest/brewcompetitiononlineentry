<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AccessPolicy $policy)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? Identity::fromSession([]);

        // Requires Slim's routing to have already run (app.php places
        // addRoutingMiddleware() before this middleware in the pipeline).
        // Falls back to the 'section' default if routing genuinely hasn't
        // happened yet (defensive - keeps this middleware usable in a
        // standalone/misconfigured context without a hard crash).
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
        } catch (\RuntimeException) {
            $route = null;
        }
        $routeName = $route?->getName() ?? 'section';
        [$routeType, $routeArg] = str_contains($routeName, ':')
            ? explode(':', $routeName, 2)
            : [$routeName, null];

        $required = match ($routeType) {
            'process' => $this->policy->requiredRoleForProcessAction(
                $request->getQueryParams()['action'] ?? null,
                $request->getQueryParams()['dbTable'] ?? null,
            ),
            'file' => $this->policy->requiredRoleForFile((string)$routeArg),
            'output' => $this->policy->requiredRoleForOutputSection(
                (string)($request->getQueryParams()['section'] ?? '')
            ),
            default => $this->policy->requiredRoleFor(
                (string)($request->getQueryParams()['section'] ?? 'default'),
                $request->getQueryParams()['go'] ?? null,
                $request->getQueryParams()['action'] ?? null,
            ),
        };

        if ($required === null || !$identity->role->satisfies($required)) {
            $response = (new ResponseFactory())->createResponse(403);
            $response->getBody()->write('Forbidden');
            return $response;
        }

        return $handler->handle($request);
    }
}
