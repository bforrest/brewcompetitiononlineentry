<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\Identity;
use OpenTelemetry\API\Trace\SpanInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

/**
 * Tags TracingMiddleware's root span with the two facts it cannot know at its
 * own process() entry: the matched route name and the resolved Identity
 * (username + role - NEVER the password, which Identity doesn't even carry).
 * Both only exist once Routing and Authentication (both INNER middleware
 * relative to Tracing - see app.php) have already run.
 *
 * Positioned in app.php's add() order right after AuthorizationMiddleware's
 * own add() call - i.e. it executes (per Slim's LIFO order) immediately
 * BEFORE Authorization, which is immediately AFTER Slim's routing and
 * Authentication have both completed. Same execution slot as the existing
 * SEF-translation closure; order relative to that closure doesn't matter
 * (neither touches the other's concerns).
 *
 * A no-op (except passing the request through unchanged) whenever no root
 * span is present on the request - e.g. a test that builds a pipeline
 * without TracingMiddleware, exactly like AuthorizationMiddleware's own
 * "missing identity attribute" fallback.
 */
final class SpanEnrichmentMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $span = $request->getAttribute(TracingMiddleware::SPAN_ATTRIBUTE);
        if ($span instanceof SpanInterface) {
            $identity = $request->getAttribute('identity') ?? Identity::fromSession([]);
            $span->setAttribute('enduser.id', $identity->username ?? '(anonymous)');
            $span->setAttribute('enduser.role', $identity->role->name);

            $routeName = $this->matchedRouteName($request);
            if ($routeName !== null) {
                $span->setAttribute('http.route', $routeName);
            }

            $section = $request->getQueryParams()['section'] ?? null;
            if ($section !== null) {
                $span->setAttribute('bcoem.section', $section);
            }
        }

        return $handler->handle($request);
    }

    private function matchedRouteName(ServerRequestInterface $request): ?string
    {
        try {
            return RouteContext::fromRequest($request)->getRoute()?->getName();
        } catch (\RuntimeException) {
            // Routing never completed - nothing to tag.
            return null;
        }
    }
}
