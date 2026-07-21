<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = Identity::fromSession($_SESSION ?? []);
        return $handler->handle($request->withAttribute('identity', $identity));
    }
}
