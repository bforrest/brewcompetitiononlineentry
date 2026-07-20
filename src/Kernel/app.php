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
    // Authentication -> Slim's own routing -> SEF-to-query-param
    // translation -> Authorization -> route handler. AuthorizationMiddleware
    // reads the MATCHED route's name (set via ->setName() on every route
    // below) to determine which policy entry governs, so Slim's routing
    // must already have run by the time it executes - hence
    // addRoutingMiddleware() sits between the add() calls for Authorization
    // and Authentication here (Task 8a fix).
    $app->add(new \Bcoem\Kernel\Middleware\AuthorizationMiddleware(
        \Bcoem\Security\AccessPolicy::fromFile(__DIR__ . '/../../config/access_policy.php')
    ));
    // Translates SEF path segments (/{section}/{go}/{action}/{id}, matched
    // by the catch-all route registered below) into $_GET/query params
    // BEFORE AuthorizationMiddleware runs. Deliberately an APP-level
    // middleware here, NOT attached to the SEF route itself (the Task 9
    // brief's original Step 4a shape used ->add() on the route) - route-
    // level middleware only runs once Slim dispatches INTO that specific
    // route's own handler chain, which is AFTER the app-level
    // AuthorizationMiddleware above has already evaluated the request.
    // Confirmed by live curl testing while building this task: with the
    // translation attached at route level, AuthorizationMiddleware saw an
    // EMPTY 'section' query param for every SEF-style URL (the real query
    // string is empty for e.g. /admin/dropoff) and silently fell back to
    // the permissive 'section:default' => Anonymous policy entry
    // regardless of the actual path requested - a full authorization
    // bypass for the app's primary URL form, the same class of bug Task 8a
    // fixed for route naming, just for query params instead. Placed here,
    // immediately after AuthorizationMiddleware's own add() call, so per
    // the LIFO rule above it executes immediately BEFORE Authorization:
    // after Slim's routing (needs the matched route's arguments) but
    // strictly before Authorization reads the query string. A no-op for
    // every other route (their matched route has no
    // section/go/action/id arguments to translate).
    $app->add(function ($request, $handler) {
        $route = \Slim\Routing\RouteContext::fromRequest($request)->getRoute();
        $args = $route?->getArguments() ?? [];
        foreach (['section', 'go', 'action', 'id'] as $key) {
            if (isset($args[$key])) {
                $_GET[$key] = $args[$key];
                $request = $request->withQueryParams([...$request->getQueryParams(), $key => $args[$key]]);
            }
        }
        return $handler->handle($request);
    });
    $app->addRoutingMiddleware();
    $app->add(new \Bcoem\Kernel\Middleware\AuthenticationMiddleware());
    $app->add(new \Bcoem\Kernel\Middleware\SessionMiddleware());
    // Outermost of all: catches Slim's own routing exceptions (e.g.
    // HttpNotFoundException for a URL matching none of the routes below -
    // a real, empirically-hit case once EVERY request funnels through this
    // front controller, e.g. a stray trailing slash or a bot-probed path)
    // and any other uncaught exception, turning it into a plain error
    // response instead of an uncaught fatal. displayErrorDetails is FALSE
    // (no stack traces to clients, matching this app's existing
    // display_errors=Off posture); logErrors/logErrorDetails TRUE (full
    // detail still reaches the server's error log for debugging).
    $app->addErrorMiddleware(false, true, true);

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

    // Explicit legacy front-controller routes. Registered BEFORE the SEF
    // catch-all below so these exact paths are never swallowed by the
    // generic /{section}/... pattern (Slim/FastRoute matches static routes
    // before dynamic ones, but registration order is what makes intent
    // clear here too - see Task 9 brief Step 4a).
    $app->get('/index.php', new \Bcoem\Legacy\LegacyPageHandler())->setName('section');
    // GET, not just POST (Task 10 fix): process.inc.php's $action/$section
    // dispatch always reads from $_GET (includes/url_variables.inc.php),
    // regardless of HTTP method - login arrives via a real POST form, but
    // logout and the auto-logout-timeout redirect are plain <a href>/
    // window.location.replace() navigations to this same file
    // (includes/authentication_nav.inc.php, pub/nav.pub.php,
    // index.pub.php's session_end_redirect), i.e. always a GET in the
    // browser. A POST-only route left every GET here unmatched by this
    // route, falling through to the SEF catch-all below instead, which
    // misparses the two literal path segments as section=includes&
    // go=process.inc.php and denies it (no such policy key) - logout has
    // never worked through the Slim front controller before this fix.
    // process.inc.php's own CSRF check (includes/process.inc.php:109) only
    // applies to POST, so GET-based actions like logout are unaffected by
    // it, matching today's real (GET, token-less) logout link.
    $app->map(['GET', 'POST'], '/includes/process.inc.php', new \Bcoem\Legacy\LegacyProcessHandler())->setName('process');
    $app->get('/', new \Bcoem\Legacy\LegacyPageHandler())->setName('section');

    // includes/output.inc.php is a self-bootstrapping side door gated on its
    // own output:section:* policy namespace (Task 3a), distinct from the
    // file:* namespace the loop above derives routes from - it needs its
    // own explicit registration here (gap found while writing Task 9; see
    // brief Step 3).
    $app->map(['GET', 'POST'], '/includes/output.inc.php', new \Bcoem\Legacy\LegacyFileHandler('includes/output.inc.php'))
        ->setName('output');

    // SEF catch-all: matches the old /section/go/action/id path shape. The
    // app-level middleware registered above (right before Authorization)
    // does the actual path-segment -> $_GET/query-param translation that
    // LegacyPageHandler and downstream legacy code expect - see that
    // middleware's own comment for why it can't live here as route-level
    // middleware. Registered LAST so it never shadows the explicit routes
    // above (Slim/FastRoute matches static routes before dynamic ones
    // regardless, but registration order keeps the intent unambiguous).
    $app->get('/{section}[/{go}[/{action}[/{id}]]]', new \Bcoem\Legacy\LegacyPageHandler())
        ->setName('section');

    return $app;
}
