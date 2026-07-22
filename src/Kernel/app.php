<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use Slim\App;

/**
 * Builds the Slim app. Does NOT run it - callers (index.php, tests) decide
 * whether to ->run() against real superglobals or ->handle() a constructed
 * PSR-7 request.
 */
function buildApp(?\Psr\Container\ContainerInterface $container = null): App
{
    // index.php builds the container first (to register legacy error logging on
    // its `legacy` channel before dispatch) and passes it in; callers that
    // don't care get a freshly built one.
    $container ??= require __DIR__ . '/container.php';
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
        \Bcoem\Security\AccessPolicy::fromFile(__DIR__ . '/../../config/access_policy.php'),
        $container->get('logger.security')
    ));
    // Task 12: tags TracingMiddleware's root span (see that middleware's own
    // docblock for why it can't tag itself) with the matched route name and
    // resolved Identity. Same execution slot as the SEF-translation closure
    // immediately below - both run after Slim's routing and Authentication,
    // strictly before Authorization - so it's placed here, right after
    // AuthorizationMiddleware's own add() call, for the same LIFO reasons.
    $app->add(new \Bcoem\Kernel\Middleware\SpanEnrichmentMiddleware());
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
    // front controller, e.g. a stray trailing slash or a bot-probed path),
    // the legacy mysqli_sql_exception thrown by any failed query (the
    // exception model that made the old `or die(mysqli_error())` idiom dead
    // code - see container.php's mysqli_report line), and any other uncaught
    // exception. The custom Bcoem\Kernel\ErrorHandler turns each into a
    // branded HTML page or {error, reference_id} JSON envelope (never a raw
    // stack trace or mysqli_error() string - closes P2-SEC-007), while still
    // logging the full trace + request context to Monolog under that same
    // reference ID.
    //
    // APP_DEBUG=1 flips on in-browser trace display (replaces hand-editing
    // paths.php's DEBUG constant). displayErrorDetails is passed to
    // addErrorMiddleware too so Slim's own logging honors it; logErrors /
    // logErrorDetails stay TRUE so full detail always reaches the log.
    $displayErrorDetails = getenv('APP_DEBUG') === '1';
    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
    $errorMiddleware->setDefaultErrorHandler(
        new \Bcoem\Kernel\ErrorHandler($container->get('logger.app'), $displayErrorDetails)
    );

    // Task 12: the TRUE outermost middleware - added last, after even
    // addErrorMiddleware() above, so per the same LIFO rule this wraps
    // ErrorMiddleware too (see TracingMiddleware's own docblock for why that
    // matters: it lets the root span's final status code reflect anything
    // ErrorMiddleware did, e.g. turning an uncaught exception into a 500).
    $app->add(new \Bcoem\Kernel\Middleware\TracingMiddleware($container->get('tracer')));

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

    // Phase 3: Entry (brewing) workflow routes.
    //
    // MUST be registered before the SEF catch-all below, and this is NOT
    // just a clarity convention (a prior comment on the catch-all claimed
    // "Slim/FastRoute matches static routes before dynamic ones regardless"
    // - that's false). nikic/fast-route compiles its dispatch table lazily
    // on the first handle() call and throws
    // FastRoute\BadRouteException("Static route ... is shadowed by
    // previously defined variable route ...") if ANY static route is
    // registered after a variable route that could match the same path
    // shape - and that compile failure takes down EVERY route in the app,
    // not just the conflicting one (confirmed empirically: with these
    // blocks after the catch-all, even /__kernel_hello 500'd). Found while
    // wiring Phase 3.2's Judging routes; moved this block and Export's
    // above the catch-all to fix it for real, not just for the new routes.
    // Lazily constructed on first use, not at buildApp()-time: Connection::class
    // (and everything built on it - EntryService, ExportService, etc.) throws
    // if $GLOBALS['connection'] isn't a real mysqli, which no route other than
    // one of these actually needs. Eagerly resolving these controllers here
    // used to mean EVERY route - even the DB-free /__kernel_hello smoke-test
    // route above - required a live DB connection just to build the app.
    $getEntryController = function () use ($container): \Bcoem\Kernel\Controller\EntryController {
        static $controller;
        return $controller ??= new \Bcoem\Kernel\Controller\EntryController(
            $container->get(\Bcoem\Domain\Entry\Service\EntryService::class)
        );
    };

    // DI\Bridge\Slim\Bridge's ControllerInvoker resolves callable parameters
    // BY NAME: it builds ['request' => ..., 'response' => ...] + the route's
    // placeholder arguments spread individually (e.g. 'id' => '5', NOT a
    // combined $args array) + request attributes, then invokes via PHP-DI's
    // Invoker. A closure parameter named anything other than exactly $request/
    // $response, or an $args catch-all instead of the placeholder's own name,
    // is simply never bound -> NotEnoughParametersException on every real
    // request. Found the same way as the routing-order bug above: these
    // closures had never actually been dispatched before.
    $app->get('/entries', fn ($request, $response) => $getEntryController()->getCreateForm($request, $response))
        ->setName('entry.create.form');
    $app->get('/entries/{id}/edit', fn ($request, $response, $id) => $getEntryController()->getEditForm($request, $response, ['id' => $id]))
        ->setName('entry.edit.form');
    $app->post('/entries', fn ($request, $response) => $getEntryController()->postCreate($request, $response))
        ->setName('entry.create');
    $app->post('/entries/{id}', fn ($request, $response, $id) => $getEntryController()->postUpdate($request, $response, ['id' => $id]))
        ->setName('entry.update');
    $app->delete('/entries/{id}', fn ($request, $response, $id) => $getEntryController()->postDelete($request, $response, ['id' => $id]))
        ->setName('entry.delete');
    $app->get('/entries/my', fn ($request, $response) => $getEntryController()->listEntries($request, $response))
        ->setName('entry.list');

    // Phase 3.4: Export workflow routes
    $getExportController = function () use ($container): \Bcoem\Kernel\Controller\ExportController {
        static $controller;
        return $controller ??= new \Bcoem\Kernel\Controller\ExportController(
            $container->get(\Bcoem\Domain\Export\Service\ExportService::class),
            $container->get(\Bcoem\Domain\Export\Service\ExportFormatterService::class)
        );
    };

    $app->get('/export', function ($request, $response) use ($getExportController) {
        $user = $request->getAttribute('identity') ?? \Bcoem\Security\Identity::fromSession($_SESSION);
        return $getExportController()->getExportForm($request, $response, $user);
    })->setName('export.form');

    $app->post('/export', function ($request, $response) use ($getExportController) {
        $user = $request->getAttribute('identity') ?? \Bcoem\Security\Identity::fromSession($_SESSION);
        return $getExportController()->postExport($request, $response, $user);
    })->setName('export.download');

    $app->get('/export/preview', function ($request, $response) use ($getExportController) {
        $user = $request->getAttribute('identity') ?? \Bcoem\Security\Identity::fromSession($_SESSION);
        return $getExportController()->getExportPreview($request, $response, $user);
    })->setName('export.preview');

    // Phase 3.7: Registration workflow routes.
    $getRegistrationController = function () use ($container): \Bcoem\Kernel\Controller\RegistrationController {
        static $controller;
        return $controller ??= new \Bcoem\Kernel\Controller\RegistrationController(
            $container->get(\Bcoem\Domain\Registration\Service\RegistrationService::class)
        );
    };

    $app->get('/register', fn ($request, $response) => $getRegistrationController()->getForm($request, $response))
        ->setName('registration.form');
    $app->post('/register', fn ($request, $response) => $getRegistrationController()->postRegister($request, $response))
        ->setName('registration.create');

    // Phase 3.2: Judging workflow routes.
    //
    // NOTE: templates/Judging/table-form.php POSTs to /judging/tables (create)
    // and /judging/tables/{id} (edit), and templates/Judging/*.php link to
    // /judging/locations - none of those three have a controller method yet
    // (JudgingController never got a postCreateTable/postUpdateTable/locations
    // handler). Not registered here rather than inventing the handler logic;
    // those links currently 404.
    // A class-string:method callable (not a pre-built instance) - DI-Bridge's
    // CallableResolver resolves JudgingController via the container lazily,
    // only when one of these routes is actually dispatched, same reasoning
    // as the Entry/Export lazy getters above.
    $judgingController = \Bcoem\Kernel\Controller\JudgingController::class;

    // JSON API (not currently called by any template; kept under /api to avoid
    // colliding with the HTML pages at the same /judging/tables[/{id}] paths).
    $app->get('/api/judging/tables', [$judgingController, 'listTables'])
        ->setName('judging.tables.list.api');
    $app->get('/api/judging/tables/{id}', [$judgingController, 'getTableDetail'])
        ->setName('judging.tables.detail.api');

    // HTML admin/judge pages (paths match templates/Judging/*.php exactly).
    $app->get('/judging/tables', [$judgingController, 'getTablesView'])
        ->setName('judging.tables.view');
    $app->get('/judging/tables/create', [$judgingController, 'getTableForm'])
        ->setName('judging.tables.create.form');
    $app->get('/judging/tables/{id}/edit', [$judgingController, 'getTableForm'])
        ->setName('judging.tables.edit.form');
    $app->get('/judging/tables/{id}', [$judgingController, 'getTableDetailView'])
        ->setName('judging.tables.detail.view');
    $app->get('/judging/tables/{id}/scoresheet', [$judgingController, 'getJudgeScoresheet'])
        ->setName('judging.scoresheet.view');

    $app->post('/judging/tables/{id}/flights', [$judgingController, 'addFlight'])
        ->setName('judging.flights.add');
    // Form uses method="post" + a hidden _method=DELETE field (no method-override
    // middleware is registered), so both verbs must route to the same handler.
    $app->map(['POST', 'DELETE'], '/judging/tables/{id}/flights/{flightId}', [$judgingController, 'removeFlight'])
        ->setName('judging.flights.remove');
    $app->post('/judging/tables/{id}/state', [$judgingController, 'transitionTableState'])
        ->setName('judging.tables.state');
    $app->post('/judging/scores', [$judgingController, 'recordScore'])
        ->setName('judging.scores.record');

    // SEF catch-all: matches the old /section/go/action/id path shape. The
    // app-level middleware registered above (right before Authorization)
    // does the actual path-segment -> $_GET/query-param translation that
    // LegacyPageHandler and downstream legacy code expect - see that
    // middleware's own comment for why it can't live here as route-level
    // middleware. Registered LAST (after every static/explicit route above)
    // because it MUST be - see the comment on the Entry routes block above.
    $app->get('/{section}[/{go}[/{action}[/{id}]]]', new \Bcoem\Legacy\LegacyPageHandler())
        ->setName('section');

    return $app;
}
