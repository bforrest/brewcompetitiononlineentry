<?php

declare(strict_types=1);

use Bcoem\Kernel\Middleware\SpanEnrichmentMiddleware;
use Bcoem\Kernel\Middleware\TracingMiddleware;
use Bcoem\Security\Identity;
use DI\Bridge\Slim\Bridge;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Exercises TracingMiddleware + SpanEnrichmentMiddleware together against a
 * real OTel SDK TracerProvider backed by an in-memory exporter (no real
 * Jaeger/collector needed - same "real objects, fake sink" approach
 * AuthorizationMiddlewareTest uses for a real Slim app instead of hand-built
 * request/attribute pairs). The actual Jaeger-UI proof is manual (see the
 * Task 12 report); these tests lock down the span-shaping LOGIC in
 * isolation: what gets tagged, when, and that it survives the
 * outermost-middleware/threaded-request-attribute design.
 */
class TracingMiddlewareTest extends TestCase
{
    private InMemoryExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
    }

    private function tracerProvider(): TracerProvider
    {
        return new TracerProvider(new SimpleSpanProcessor($this->exporter));
    }

    /**
     * Mirrors app.php's real ordering: TracingMiddleware outermost,
     * SpanEnrichmentMiddleware right before an identity/routing stand-in,
     * matching the LIFO add() order documented in both classes.
     */
    private function buildTestApp(Identity $identity, string $routeName): App
    {
        $app = Bridge::create(new \DI\Container());

        $app->add(new SpanEnrichmentMiddleware());
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($identity): ResponseInterface {
            return $handler->handle($request->withAttribute('identity', $identity));
        });
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($this->tracerProvider()->getTracer('test')));

        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->get('/test-route', $handler)->setName($routeName);

        return $app;
    }

    private function get(App $app, string $uri): ResponseInterface
    {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri)->withQueryParams($query);
        return $app->handle($request);
    }

    public function test_a_successful_request_produces_one_span_tagged_with_route_and_role(): void
    {
        $app = $this->buildTestApp(
            Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']),
            'section'
        );
        $response = $this->get($app, '/test-route?section=admin');

        $this->assertSame(200, $response->getStatusCode());

        $spans = $this->exporter->getSpans();
        $this->assertCount(1, $spans);
        $span = $spans[0];

        $this->assertSame(200, $span->getAttributes()->get('http.status_code'));
        $this->assertSame('SuperAdmin', $span->getAttributes()->get('enduser.role'));
        $this->assertSame('a@example.com', $span->getAttributes()->get('enduser.id'));
        $this->assertSame('section', $span->getAttributes()->get('http.route'));
        $this->assertSame('admin', $span->getAttributes()->get('bcoem.section'));
    }

    public function test_an_anonymous_request_is_tagged_with_the_anonymous_role_and_no_username(): void
    {
        $app = $this->buildTestApp(Identity::fromSession([]), 'section');
        $response = $this->get($app, '/test-route?section=contact');

        $this->assertSame(200, $response->getStatusCode());

        $span = $this->exporter->getSpans()[0];
        $this->assertSame('Anonymous', $span->getAttributes()->get('enduser.role'));
        $this->assertSame('(anonymous)', $span->getAttributes()->get('enduser.id'));
    }

    public function test_a_5xx_response_marks_the_span_status_as_error(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($this->tracerProvider()->getTracer('test')));
        $app->get('/boom', function ($request, $response) {
            return $response->withStatus(500);
        });

        $response = $this->get($app, '/boom');

        $this->assertSame(500, $response->getStatusCode());
        $span = $this->exporter->getSpans()[0];
        $this->assertSame(500, $span->getAttributes()->get('http.status_code'));
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
    }

    public function test_a_4xx_response_does_not_mark_the_span_as_error(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($this->tracerProvider()->getTracer('test')));
        $app->get('/denied', function ($request, $response) {
            return $response->withStatus(403);
        });

        $response = $this->get($app, '/denied');

        $this->assertSame(403, $response->getStatusCode());
        $span = $this->exporter->getSpans()[0];
        $this->assertSame(403, $span->getAttributes()->get('http.status_code'));
        $this->assertNotSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
    }

    public function test_an_uncaught_exception_is_recorded_on_the_span_and_rethrown(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($this->tracerProvider()->getTracer('test')));
        $app->get('/throws', function () {
            throw new \RuntimeException('kaboom');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kaboom');
        try {
            $this->get($app, '/throws');
        } finally {
            $span = $this->exporter->getSpans()[0];
            $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
            $events = $span->getEvents();
            $this->assertNotEmpty($events);
            $this->assertSame('exception', $events[0]->getName());
        }
    }

    public function test_span_enrichment_without_a_root_span_present_is_a_harmless_no_op(): void
    {
        // No TracingMiddleware in this pipeline at all - simulates a
        // misconfigured/partial pipeline (same defensive posture as
        // AuthorizationMiddleware's "missing identity attribute" test).
        $app = Bridge::create(new \DI\Container());
        $app->add(new SpanEnrichmentMiddleware());
        $app->addRoutingMiddleware();
        $handler = function ($request, $response) {
            $response->getBody()->write('ok');
            return $response;
        };
        $app->get('/test-route', $handler)->setName('section');

        $response = $this->get($app, '/test-route?section=contact');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }
}
