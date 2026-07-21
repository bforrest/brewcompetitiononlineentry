<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once ROOT . 'src/Kernel/app.php';

class HelloWorldRouteTest extends TestCase
{
    public function test_kernel_hello_route_responds_ok(): void
    {
        $app = buildApp();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/__kernel_hello');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string)$response->getBody());
    }
}
