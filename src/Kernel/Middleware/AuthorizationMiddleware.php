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

final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AccessPolicy $policy)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Identity $identity */
        $identity = $request->getAttribute('identity');
        $routeType = $request->getAttribute('routeType', 'section');

        $required = match ($routeType) {
            'process' => $this->policy->requiredRoleForProcessAction(
                $request->getQueryParams()['action'] ?? null,
                $request->getQueryParams()['dbTable'] ?? null,
            ),
            'file' => $this->policy->requiredRoleForFile((string)$request->getAttribute('routeFile')),
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
