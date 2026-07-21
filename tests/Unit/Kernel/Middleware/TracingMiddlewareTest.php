<?php

declare(strict_types=1);

use Bcoem\Kernel\Middleware\SpanEnrichmentMiddleware;
use Bcoem\Kernel\Middleware\TracingMiddleware;
use Bcoem\Security\Identity;
use DI\Bridge\Slim\Bridge;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
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
 *
 * Task 12 review fix round 1: every test above this comment only ever has
 * ONE span in flight and never asserts a parent/child relationship - a real
 * evidentiary gap a reviewer correctly flagged (the report's "clean,
 * correctly-parented" claim wasn't backed by anything these tests actually
 * checked). test_a_span_started_while_the_root_is_active_nests_as_its_child()
 * below closes that gap: it proves TracingMiddleware's own mechanism - calling
 * $span->activate() (not just stashing the SpanInterface as a request
 * attribute) - genuinely makes the root span OTel's ambient
 * Context::getCurrent() span for the rest of the request, exactly the
 * mechanism auto-instrumented code (mysqli's hooks included) relies on to
 * parent ITS OWN spans, without needing the native ext-opentelemetry
 * extension or a real Jaeger collector to prove it.
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
            // Task 12 review fix round 1 (minor finding): tagFinalStatus()'s
            // own call, right after this, used to unconditionally overwrite
            // this catch block's setStatus(ERROR, 'kaboom') with a
            // description-less one - proving the exception's message
            // actually survives closes that gap.
            $this->assertSame('kaboom', $span->getStatus()->getDescription());
            $events = $span->getEvents();
            $this->assertNotEmpty($events);
            $this->assertSame('exception', $events[0]->getName());
        }
    }

    /**
     * Task 12 review fix round 1 (minor finding): reproduces the cross-class
     * version of the same clobbering bug - ErrorHandler.php runs INSIDE
     * $handler->handle() (it's what ErrorMiddleware, which is INNER to
     * Tracing, calls) and sets a specific exception message as the span's
     * status description before TracingMiddleware ever sees the response.
     * This test stands in for that by having the route handler itself call
     * setStatus() with a description - exactly what TracingMiddleware
     * observes from ErrorHandler's perspective - then returning a 500
     * response, and asserts tagFinalStatus() leaves that description alone.
     */
    public function test_tag_final_status_does_not_clobber_a_description_an_inner_layer_already_set(): void
    {
        $app = Bridge::create(new \DI\Container());
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($this->tracerProvider()->getTracer('test')));
        $app->get('/handled-error', function ($request, $response) {
            \OpenTelemetry\API\Trace\Span::getCurrent()->setStatus(
                StatusCode::STATUS_ERROR,
                'Access denied for user (using password: YES)'
            );
            return $response->withStatus(500);
        });

        $response = $this->get($app, '/handled-error');

        $this->assertSame(500, $response->getStatusCode());
        $span = $this->exporter->getSpans()[0];
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertSame(
            'Access denied for user (using password: YES)',
            $span->getStatus()->getDescription()
        );
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

    /**
     * Task 12 review fix round 1 - the parent/child regression the report
     * was missing (reviewer finding #2) and the direct proof that
     * TracingMiddleware's own span-nesting mechanism is sound (reviewer
     * finding #1). Root cause of the live-Jaeger "ghost ancestor" bug turned
     * out NOT to be a missing Context activation in TracingMiddleware (it was
     * already calling $span->activate() before this review round - verified
     * via git blame) - it was a TypeError thrown inside
     * opentelemetry-auto-mysqli's own constructPostHook() (site/config.php
     * and docker/config.php passed ini_get('mysqli.default_port')'s STRING
     * return value to `new mysqli(...)`, and MySqliTracker::
     * storeMySqliAttributes()'s strict `?int $port` parameter type-errors on
     * it), which aborted that hook before it reached endSpan() and leaked an
     * un-detached Context scope for the rest of the request. Fixed at the
     * call site (both config.php files now cast to (int)); see
     * task-12-report.md's "Review fix round 1" section for the full
     * investigation. This test doesn't touch mysqli or the native extension
     * at all - it proves the piece that's actually ours: that starting a
     * second span while TracingMiddleware's root is active (exactly what any
     * ambient-Context-reading auto-instrumentation does, mysqli's hooks
     * included) makes that second span a true child of the root, with no
     * live Jaeger/collector needed to see it.
     */
    public function test_a_span_started_while_the_root_is_active_nests_as_its_child(): void
    {
        $tracerProvider = $this->tracerProvider();
        $tracer = $tracerProvider->getTracer('test');

        $app = Bridge::create(new \DI\Container());
        $app->addRoutingMiddleware();
        $app->add(new TracingMiddleware($tracer));
        $app->get('/test-route', function ($request, $response) use ($tracer) {
            // Mirrors exactly what MySqliInstrumentation::startSpan() does
            // for every auto-instrumented mysqli call: no explicit parent is
            // set, so the SDK's SpanBuilder::startSpan() resolves the parent
            // via Context::resolve(null) -> Context::getCurrent() - the
            // ambient span TracingMiddleware's own $span->activate() put
            // there. If TracingMiddleware ever regresses to only stashing the
            // SpanInterface as a request attribute (never activating it),
            // this span would come back with an INVALID (root-less) parent
            // context instead of the real root - exactly the bug this round
            // investigated.
            $childSpan = $tracer->spanBuilder('mysqli_query')->setSpanKind(SpanKind::KIND_CLIENT)->startSpan();
            $childSpan->end();

            $response->getBody()->write('ok');
            return $response;
        });

        $response = $this->get($app, '/test-route');

        $this->assertSame(200, $response->getStatusCode());

        // Export order is end() order, not start() order: the child span
        // ends inside the route handler, strictly before TracingMiddleware's
        // own finally block ends the root span once $handler->handle()
        // returns - so the child is exported first.
        $spans = $this->exporter->getSpans();
        $this->assertCount(2, $spans);

        $child = $spans[0];
        $root = $spans[1];
        $this->assertSame('mysqli_query', $child->getName());
        $this->assertSame('GET /test-route', $root->getName());

        $this->assertTrue(
            $child->getParentContext()->isValid(),
            'child span must have a valid parent context - not root-less'
        );
        $this->assertSame(
            $root->getContext()->getSpanId(),
            $child->getParentContext()->getSpanId(),
            'child span must nest directly under the root span TracingMiddleware activated'
        );
        $this->assertSame(
            $root->getContext()->getTraceId(),
            $child->getContext()->getTraceId(),
            'child span must share the root span\'s trace ID'
        );
    }
}
