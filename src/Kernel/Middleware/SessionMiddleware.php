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
            // Mirror paths.php:222-223's exact branching: empty() (not ??)
            // treats '' the same as unset, and the fallback is __FILE__ (this
            // middleware's own file, standing in for paths.php's __FILE__),
            // not __DIR__. site/config.php's shipped default sets
            // $installation_id = '' - since '' is set but empty, a bare ??
            // would compute md5('') here while paths.php computes
            // md5(__FILE__), starting a DIFFERENT session than legacy code.
            $installationId = $GLOBALS['installation_id'] ?? '';
            if (empty($installationId)) $installationId = __FILE__;
            session_name(md5($installationId));
            session_start();
        }
        return $handler->handle($request);
    }
}
