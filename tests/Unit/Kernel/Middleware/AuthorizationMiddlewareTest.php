<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\AuthorizationMiddleware;
use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use DI\Bridge\Slim\Bridge;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthorizationMiddlewareTest extends TestCase
{
    private function policy(): AccessPolicy
    {
        return AccessPolicy::fromFile(ROOT . 'config/access_policy.php');
    }

    /**
     * Builds a real Slim app wired with the exact same middleware order as
     * production src/Kernel/app.php (Authorization -> routing ->
     * Authentication-stand-in -> handler), so these tests exercise the real
     * pipeline ordering, not a hand-constructed request/attribute pair.
     */
    private function buildTestApp(Identity $identity, string $routeName): App
    {
        $app = Bridge::create(new \DI\Container());

        $app->add(new AuthorizationMiddleware($this->policy()));
        $app->addRoutingMiddleware();
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($identity): ResponseInterface {
            return $handler->handle($request->withAttribute('identity', $identity));
        });

        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->map(['GET', 'POST'], '/test-route', $handler)->setName($routeName);

        return $app;
    }

    private function get(App $app, string $uri): ResponseInterface
    {
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri)->withQueryParams($query);
        return $app->handle($request);
    }

    public function test_anonymous_may_reach_a_public_section(): void
    {
        $app = $this->buildTestApp(Identity::fromSession([]), 'section');
        $response = $this->get($app, '/test-route?section=contact');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string)$response->getBody());
    }

    public function test_anonymous_is_denied_the_admin_section(): void
    {
        $app = $this->buildTestApp(Identity::fromSession([]), 'section');
        $response = $this->get($app, '/test-route?section=admin');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_entrant_is_denied_a_super_admin_only_go(): void
    {
        $app = $this->buildTestApp(
            Identity::fromSession(['loginUsername' => 'e@example.com', 'userLevel' => '3']),
            'section'
        );
        $response = $this->get($app, '/test-route?section=admin&go=styles');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_super_admin_may_reach_a_super_admin_only_go(): void
    {
        $app = $this->buildTestApp(
            Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']),
            'section'
        );
        $response = $this->get($app, '/test-route?section=admin&go=styles');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_undeclared_section_is_denied_fail_closed(): void
    {
        $app = $this->buildTestApp(
            Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']),
            'section'
        );
        // Even a super-admin is denied - undeclared means denied, no exceptions.
        $response = $this->get($app, '/test-route?section=brand-new-undeclared-section');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_process_route_checks_process_action_via_query_params(): void
    {
        $app = $this->buildTestApp(Identity::fromSession([]), 'process');
        $response = $this->get($app, '/test-route?action=login');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_route_allows_when_the_files_declared_role_is_satisfied(): void
    {
        // qr.php is Role::Anonymous in the real policy map.
        $app = $this->buildTestApp(Identity::fromSession([]), 'file:qr.php');
        $response = $this->get($app, '/test-route');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_route_denies_when_the_files_declared_role_is_not_satisfied(): void
    {
        // ajax/purge.ajax.php is Role::SuperAdmin in the real policy map.
        $app = $this->buildTestApp(
            Identity::fromSession(['loginUsername' => 'e@example.com', 'userLevel' => '3']),
            'file:ajax/purge.ajax.php'
        );
        $response = $this->get($app, '/test-route');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_missing_identity_attribute_denies_a_privileged_route(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->add(new AuthorizationMiddleware($this->policy()));
        $app->addRoutingMiddleware();
        // Deliberately NOT adding the identity-attaching middleware -
        // simulates a misconfigured pipeline.
        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->get('/test-route', $handler)->setName('section');

        $response = $this->get($app, '/test-route?section=admin');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_a_matched_route_with_no_name_denies_rather_than_falling_back_to_a_permissive_default(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->add(new AuthorizationMiddleware($this->policy()));
        $app->addRoutingMiddleware();
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($request->withAttribute('identity', Identity::fromSession([])));
        });
        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        // Deliberately no ->setName(...) - simulates a route registered
        // without following the naming contract. Even for a public-looking
        // URL with no ?section, this must NOT fall back to the permissive
        // section:default => Anonymous policy.
        $app->get('/test-route', $handler);

        $response = $this->get($app, '/test-route');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_a_denial_is_logged_to_the_security_channel_with_route_and_identity_context(): void
    {
        $testHandler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($testHandler);

        $app = Bridge::create(new \DI\Container());
        $app->add(new AuthorizationMiddleware($this->policy(), $logger));
        $app->addRoutingMiddleware();
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($request->withAttribute(
                'identity',
                Identity::fromSession(['loginUsername' => 'e@example.com', 'userLevel' => '3'])
            ));
        });
        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->get('/test-route', $handler)->setName('section');

        $response = $this->get($app, '/test-route?section=admin');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertTrue($testHandler->hasWarningRecords());
        $record = $testHandler->getRecords()[0];
        $this->assertSame('Authorization denied', $record->message);
        $this->assertSame('section', $record->context['route']);
        $this->assertSame('Entrant', $record->context['role']);
        $this->assertSame('e@example.com', $record->context['username']);
    }

    public function test_a_successful_authorization_logs_nothing(): void
    {
        $testHandler = new TestHandler();
        $logger = new Logger('security');
        $logger->pushHandler($testHandler);

        $app = Bridge::create(new \DI\Container());
        $app->add(new AuthorizationMiddleware($this->policy(), $logger));
        $app->addRoutingMiddleware();
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($request->withAttribute('identity', Identity::fromSession([])));
        });
        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->get('/test-route', $handler)->setName('section');

        $response = $this->get($app, '/test-route?section=contact');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $testHandler->getRecords());
    }
}
