<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel;

use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\LandingPage\Service\LandingPageCopyAdapter;
use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Kernel\Controller\LandingPageController;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Legacy\LegacyPageHandler;
use Bcoem\Security\Role;
use DI\Container;
use OpenTelemetry\API\Globals;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once ROOT . 'src/Kernel/app.php';

final class LandingPageRouteTest extends TestCase
{
    public function test_root_uses_the_modern_landing_route(): void
    {
        $app = buildApp($this->containerWithLandingController());
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-modern-landing-page="true"', (string) $response->getBody());
    }

    public function test_index_php_remains_the_legacy_reference(): void
    {
        $app = buildApp($this->containerWithLandingController());
        $routes = $this->routeSignatures($app);

        self::assertSame('landing.page', $routes['GET /']);
        self::assertSame('section', $routes['GET /index.php']);
        self::assertSame('registration.form', $routes['GET /register']);
        self::assertSame('section', $routes['GET /{section}[/{go}[/{action}[/{id}]]]']);
        self::assertInstanceOf(LegacyPageHandler::class, $this->routeCallables($app)['GET /index.php']);
    }

    public function test_root_with_a_legacy_query_string_does_not_render_the_modern_landing_page(): void
    {
        $app = buildApp($this->containerWithLandingController());
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/?section=login'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('legacy root query', (string) $response->getBody());
        self::assertStringNotContainsString('data-modern-landing-page="true"', (string) $response->getBody());
    }

    public function test_index_php_uses_the_legacy_dispatch_for_bare_and_query_string_requests(): void
    {
        $app = buildApp($this->containerWithLandingController());
        $this->route($app, 'GET /index.php')->setCallable(function ($request, $response) {
            $section = $request->getQueryParams()['section'] ?? 'default';
            $response->getBody()->write("legacy index {$section}");
            return $response;
        });

        $factory = new ServerRequestFactory();
        $bareResponse = $app->handle($factory->createServerRequest('GET', '/index.php'));
        $queryResponse = $app->handle(
            $factory->createServerRequest('GET', '/index.php?section=login'),
        );

        self::assertSame(200, $bareResponse->getStatusCode());
        self::assertSame('legacy index default', (string) $bareResponse->getBody());
        self::assertSame(200, $queryResponse->getStatusCode());
        self::assertSame('legacy index login', (string) $queryResponse->getBody());
    }

    public function test_landing_page_route_is_anonymous(): void
    {
        $policy = require ROOT . 'config/access_policy.php';

        self::assertSame(Role::Anonymous, $policy['landing.page']);
    }

    private function containerWithLandingController(): ContainerInterface
    {
        $container = new Container();
        $container->set('logger.security', new NullLogger());
        $container->set('logger.app', new NullLogger());
        $container->set('tracer', Globals::tracerProvider()->getTracer('test'));
        $container->set(LandingPageController::class, $this->landingPageController());
        $container->set(LegacyPageHandler::class, new class {
            public function __invoke($request, $response)
            {
                $response->getBody()->write('legacy root query');
                return $response;
            }
        });

        return $container;
    }

    private function landingPageController(): LandingPageController
    {
        $now = time();
        $repository = $this->createMock(LandingPageReadRepository::class);
        $repository->method('contestOverview')->willReturn(
            new ContestOverview('Fixture Competition', 'Fixture Host', 'https://host.example.test', null, null),
        );
        $repository->method('competitionWindows')->willReturn(
            new CompetitionWindows(
                $now - 3600,
                $now + 3600,
                $now - 3600,
                $now + 3600,
                $now - 3600,
                $now + 3600,
                null,
                null,
                null,
                null,
            ),
        );
        $repository->method('competitionLimits')->willReturn(
            new CompetitionLimits(5, 3, 100, 80, 90),
        );
        $repository->method('judgingProgress')->willReturn(
            new JudgingProgress(false, false, false, 0),
        );
        $repository->method('locations')->willReturn(
            new CompetitionLocations(null, null, null, null, null, null),
        );
        $repository->method('contacts')->willReturn([]);
        $repository->method('sponsors')->willReturn([]);
        $repository->method('visibleArchives')->willReturn([]);
        $repository->method('winnerSummary')->willReturn(
            new WinnerSummary(WinnerMethod::Overall, '', []),
        );

        return new LandingPageController(
            new LandingPageService($repository, new LandingPageCopyAdapter()),
            new LayoutRenderer(),
        );
    }

    /** @return array<string, string> */
    private function routeSignatures(App $app): array
    {
        $signatures = [];
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $signatures["{$method} {$route->getPattern()}"] = $route->getName();
            }
        }

        return $signatures;
    }

    /** @return array<string, callable|array|string> */
    private function routeCallables(App $app): array
    {
        $callables = [];
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                $callables["{$method} {$route->getPattern()}"] = $route->getCallable();
            }
        }

        return $callables;
    }

    private function route(App $app, string $signature): RouteInterface
    {
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            foreach ($route->getMethods() as $method) {
                if ("{$method} {$route->getPattern()}" === $signature) {
                    return $route;
                }
            }
        }

        throw new \LogicException("Route {$signature} was not registered.");
    }
}
