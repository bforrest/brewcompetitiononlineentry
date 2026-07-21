<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;

/**
 * Adds the active OTel trace ID to every log record's `extra`, letting a
 * single request's Monolog lines (any of the app/security/legacy channels -
 * see container.php) be correlated with its trace in the Jaeger UI (Task 12).
 *
 * Reads the ambient "current span" via Span::getCurrent() - the same
 * OTel Context storage TracingMiddleware activates via $span->activate() -
 * rather than anything request-specific, so it works from any code path,
 * including legacy code that never sees a PSR-7 request at all.
 *
 * A complete no-op (no `trace_id` key added) when there is no active span -
 * e.g. every CLI/PHPUnit run, and any shared-hosting deployment where the
 * OTel SDK's env-driven autoload never activates (see docker/apache/vhost.conf's
 * OTEL_PHP_AUTOLOAD_ENABLED, which is Docker-dev-only) - Span::getCurrent()
 * then returns a non-recording span whose context is invalid.
 */
final class TraceIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = Span::getCurrent()->getContext();
        if (!$context->isValid()) {
            return $record;
        }

        $record->extra['trace_id'] = $context->getTraceId();
        return $record;
    }
}
