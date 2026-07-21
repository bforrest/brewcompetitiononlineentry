<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\SessionMiddleware;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class SessionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure no session is active before each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($GLOBALS['installation_id']);
    }

    public function test_session_name_matches_paths_php_derivation_for_empty_installation_id(): void
    {
        // Mirrors site/config.php's shipped default: $installation_id = ''
        $GLOBALS['installation_id'] = '';
        $middleware = new SessionMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $next = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $middleware->process($request, $next);

        $sessionMiddlewareFile = (new \ReflectionClass(SessionMiddleware::class))->getFileName();
        $this->assertSame(md5($sessionMiddlewareFile), session_name());
    }

    public function test_session_name_uses_installation_id_when_non_empty(): void
    {
        $GLOBALS['installation_id'] = 'docker-local';
        $middleware = new SessionMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $next = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $middleware->process($request, $next);

        $this->assertSame(md5('docker-local'), session_name());
    }
}
