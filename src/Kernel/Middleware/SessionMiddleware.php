<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Starts the SAME named session legacy code expects (paths.php:222,239 uses
 * md5($installation_id) or md5(__FILE__) as the session name) BEFORE any
 * legacy bridge runs, so $_SESSION is populated identically to today. Legacy
 * code's own session_start() calls become no-ops (PHP_SESSION_ACTIVE guard
 * already present throughout the codebase).
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            $installationId = $GLOBALS['installation_id'] ?? __DIR__;
            session_name(md5($installationId));
            session_start();
        }
        return $handler->handle($request);
    }
}
