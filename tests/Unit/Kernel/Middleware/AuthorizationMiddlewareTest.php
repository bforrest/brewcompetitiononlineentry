<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\AuthorizationMiddleware;
use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

final class StubHandler implements RequestHandlerInterface
{
    public bool $called = false;
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;
        return (new ResponseFactory())->createResponse(200);
    }
}

class AuthorizationMiddlewareTest extends TestCase
{
    private function policy(): AccessPolicy
    {
        return AccessPolicy::fromFile(ROOT . 'config/access_policy.php');
    }

    public function test_anonymous_may_reach_a_public_section(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=contact')
            ->withQueryParams(['section' => 'contact'])
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_anonymous_is_denied_the_admin_section(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin')
            ->withQueryParams(['section' => 'admin'])
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_entrant_is_denied_a_super_admin_only_go(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin&go=styles')
            ->withQueryParams(['section' => 'admin', 'go' => 'styles'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'e@example.com', 'userLevel' => '3']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_super_admin_may_reach_a_super_admin_only_go(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin&go=styles')
            ->withQueryParams(['section' => 'admin', 'go' => 'styles'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_undeclared_section_is_denied_fail_closed(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=brand-new-undeclared-section')
            ->withQueryParams(['section' => 'brand-new-undeclared-section'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        // Even a super-admin is denied - undeclared means denied, no exceptions.
        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_process_route_checks_process_action_attribute(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/includes/process.inc.php?action=login')
            ->withQueryParams(['action' => 'login'])
            ->withAttribute('routeType', 'process')
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_route_checks_file_attribute(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/qr.php')
            ->withAttribute('routeType', 'file')
            ->withAttribute('routeFile', 'qr.php')
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }
}
