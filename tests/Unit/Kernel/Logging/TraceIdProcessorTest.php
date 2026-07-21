<?php

declare(strict_types=1);

use Bcoem\Kernel\Logging\TraceIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

class TraceIdProcessorTest extends TestCase
{
    private function record(): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, 'a message');
    }

    public function test_no_active_span_leaves_the_record_untouched(): void
    {
        $processor = new TraceIdProcessor();
        $record = ($processor)($this->record());

        $this->assertArrayNotHasKey('trace_id', $record->extra);
    }

    public function test_an_active_span_adds_its_trace_id_to_extra(): void
    {
        $exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $tracer = $tracerProvider->getTracer('test');
        $processor = new TraceIdProcessor();

        $span = $tracer->spanBuilder('root')->startSpan();
        $scope = $span->activate();
        try {
            $record = ($processor)($this->record());
        } finally {
            $scope->detach();
            $span->end();
        }

        $this->assertArrayHasKey('trace_id', $record->extra);
        $this->assertSame($span->getContext()->getTraceId(), $record->extra['trace_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $record->extra['trace_id']);
    }
}
