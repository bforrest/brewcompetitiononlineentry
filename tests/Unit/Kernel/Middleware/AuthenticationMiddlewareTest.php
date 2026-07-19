<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\AuthenticationMiddleware;
use Bcoem\Security\Role;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthenticationMiddlewareTest extends TestCase
{
    public function test_attaches_identity_from_session_superglobal(): void
    {
        $_SESSION = ['loginUsername' => 'admin@example.com', 'userLevel' => '1'];
        $middleware = new AuthenticationMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php');

        $captured = null;
        $next = new class($captured) implements RequestHandlerInterface {
            public function __construct(public mixed &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute('identity');
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $middleware->process($request, $next);

        $this->assertTrue($next->captured->loggedIn);
        $this->assertSame(Role::Admin, $next->captured->role);
    }
}
