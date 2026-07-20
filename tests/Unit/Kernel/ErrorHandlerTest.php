<?php

declare(strict_types=1);

use Bcoem\Kernel\ErrorHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;

class ErrorHandlerTest extends TestCase
{
    private function request(string $accept = 'text/html', string $xhr = ''): \Psr\Http\Message\ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/whatever')
            ->withHeader('Accept', $accept);
        if ($xhr !== '') {
            $request = $request->withHeader('X-Requested-With', $xhr);
        }
        return $request;
    }

    public function test_html_production_page_hides_the_exception_message_but_shows_a_reference_id(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: false);

        $exception = new \RuntimeException('SQLSTATE secret table baseline_super_secret does not exist');
        $response = ($handler)($this->request(), $exception, false, true, true);

        $this->assertSame(500, $response->getStatusCode());
        $body = (string) $response->getBody();
        // The raw exception message must NOT leak to the client in production.
        $this->assertStringNotContainsString('baseline_super_secret', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        // But a short hex reference ID must be present for support correlation.
        $this->assertMatchesRegularExpression('/[0-9a-f]{8}/', $body);
    }

    public function test_full_trace_and_reference_id_are_logged(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: false);

        $exception = new \RuntimeException('boom detail');
        ($handler)($this->request(), $exception, false, true, true);

        $this->assertTrue($log->hasErrorRecords());
        $records = $log->getRecords();
        $context = $records[0]->context;
        $this->assertArrayHasKey('reference_id', $context);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $context['reference_id']);
        // Full detail (the exception itself) is logged even in production mode.
        $this->assertSame($exception, $context['exception']);
    }

    public function test_debug_mode_reveals_the_exception_message_in_the_page(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: true);

        $exception = new \RuntimeException('the gory details');
        $response = ($handler)($this->request(), $exception, true, true, true);

        $this->assertStringContainsString('the gory details', (string) $response->getBody());
    }

    public function test_json_envelope_for_a_request_that_accepts_json(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: false);

        $response = ($handler)($this->request('application/json'), new \RuntimeException('nope'), false, true, true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertArrayHasKey('reference_id', $decoded);
        $this->assertStringNotContainsString('nope', (string) $response->getBody());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $decoded['reference_id']);
    }

    public function test_json_envelope_for_an_xmlhttprequest(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: false);

        $response = ($handler)(
            $this->request('text/html', 'XMLHttpRequest'),
            new \RuntimeException('nope'),
            false,
            true,
            true
        );

        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_http_exception_status_code_is_preserved(): void
    {
        $log = new TestHandler();
        $handler = new ErrorHandler(new Logger('app', [$log]), displayErrorDetails: false);

        $request = $this->request();
        $response = ($handler)($request, new HttpNotFoundException($request), false, true, true);

        $this->assertSame(404, $response->getStatusCode());
    }
}
