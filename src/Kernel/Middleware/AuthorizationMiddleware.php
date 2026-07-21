<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(private readonly AccessPolicy $policy, ?LoggerInterface $logger = null)
    {
        // Defaults to a no-op logger so every existing call site (and every
        // test in this suite) that constructs this middleware without a
        // logger keeps working unchanged - only app.php's production wiring
        // passes the real 'security' channel.
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? Identity::fromSession([]);

        // Requires Slim's routing to have already run (app.php places
        // addRoutingMiddleware() before this middleware in the pipeline).
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
        } catch (\RuntimeException) {
            $route = null;
        }

        // A matched route with no name, or no matched route at all (routing
        // hasn't run - a misconfigured pipeline), means this middleware
        // cannot determine which policy governs. A security gate that can't
        // identify the request must fail closed, never guess a permissive
        // default.
        $routeName = $route?->getName();
        if ($routeName === null) {
            return $this->deny($identity, $request, 'no-named-route-matched');
        }

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
            return $this->deny($identity, $request, $routeName);
        }

        return $handler->handle($request);
    }

    private function deny(Identity $identity, ServerRequestInterface $request, string $routeName): ResponseInterface
    {
        $this->logger->warning('Authorization denied', [
            'route' => $routeName,
            'role' => $identity->role->name,
            'username' => $identity->username,
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
        ]);

        $response = (new ResponseFactory())->createResponse(403);
        $response->getBody()->write('Forbidden');
        return $response;
    }
}
