<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Opens one root span per request (Task 12). Registered LAST in
 * src/Kernel/app.php - AFTER addErrorMiddleware()'s own add() call - so per
 * Slim's LIFO add() order (last add() = outermost = runs first for the
 * request) this is the true outermost middleware, wrapping even
 * ErrorMiddleware. That matters for two reasons:
 *
 *   1. The final response status code this middleware observes already
 *      reflects anything ErrorMiddleware did (e.g. turning an uncaught
 *      mysqli_sql_exception into a 500) - there's nothing "more final" than
 *      what's about to go back to the client.
 *   2. Activating the span (self::process() calls $span->activate() before
 *      $handler->handle()) makes it the ambient "current" OTel span for the
 *      ENTIRE rest of the pipeline, including Session/Authentication/Routing/
 *      Authorization/the route handler and anything they call (legacy
 *      mysqli_query() calls included, once open-telemetry/opentelemetry-auto-mysqli
 *      + the native ext-opentelemetry extension are active - see the
 *      Dockerfile and docker-compose.yml). Auto-instrumented libraries look
 *      up the "current" span via OTel's own Context storage, not via any
 *      request attribute - so simply being outermost + activating the span is
 *      what makes every mysqli_query() during this request nest UNDER this
 *      span, with zero code changes anywhere in sections/admin/lib/.
 *
 * The one fact this middleware genuinely cannot know at its own process()
 * entry is WHO is making the request and WHICH route matched - Authentication
 * and Routing are both INNER middleware (they run after this one, deeper in
 * the pipeline - see app.php), and PSR-15 responses don't carry request
 * attributes back out to an outer middleware once $handler->handle() returns.
 * So this middleware doesn't try to read 'identity' itself. Instead it hands
 * the (mutable) SpanInterface object itself to every inner layer via a
 * request attribute (self::SPAN_ATTRIBUTE) - SpanEnrichmentMiddleware, placed
 * right after routing/authentication have run, reads that same attribute and
 * tags THIS span directly (SpanInterface::setAttribute() mutates the span
 * object in place; the object reference is what's shared, not the immutable
 * PSR-7 request). ErrorHandler does the same to record an exception
 * ErrorMiddleware caught.
 */
final class TracingMiddleware implements MiddlewareInterface
{
    public const SPAN_ATTRIBUTE = 'otel.root_span';

    public function __construct(private readonly TracerInterface $tracer)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $span = $this->tracer->spanBuilder(sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->getMethod())
            ->setAttribute('http.target', (string) $request->getUri()->withQuery('')->withFragment(''))
            ->startSpan();

        $scope = $span->activate();
        try {
            $response = $handler->handle($request->withAttribute(self::SPAN_ATTRIBUTE, $span));
            $this->tagFinalStatus($span, $response->getStatusCode());
            return $response;
        } catch (Throwable $exception) {
            // Defensive only: everything below this middleware is already
            // wrapped by ErrorMiddleware (see app.php), so in practice an
            // exception should never reach here uncaught. If it somehow does
            // (e.g. a bug in ErrorMiddleware itself), still record it rather
            // than let the span end with no error information.
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            $this->tagFinalStatus($span, 500);
            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    private function tagFinalStatus(SpanInterface $span, int $statusCode): void
    {
        $span->setAttribute('http.status_code', $statusCode);
        if ($statusCode >= 500) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }
    }
}
