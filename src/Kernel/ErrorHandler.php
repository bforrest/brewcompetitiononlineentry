<?php

declare(strict_types=1);

namespace Bcoem\Kernel;

use Bcoem\Kernel\Middleware\TracingMiddleware;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Central error handler for the Slim ErrorMiddleware (registered in
 * src/Kernel/app.php via setDefaultErrorHandler).
 *
 * Every uncaught exception - a legacy mysqli_sql_exception, a routing
 * HttpNotFoundException, or anything else - lands here and is turned into a
 * clean response instead of a raw stack trace or blank page:
 *
 *   - A short hex reference ID (bin2hex(random_bytes(4))) is minted per error.
 *   - The full exception + request context is logged under that ID via Monolog,
 *     so operators can find the details a user only sees as a reference ID.
 *   - HTML requests get a branded error page; AJAX/JSON requests get a
 *     {error, reference_id} JSON envelope (detected by the file:ajax/* route
 *     name convention, an application/json Accept header, or an
 *     X-Requested-With: XMLHttpRequest header).
 *   - In debug mode ($displayErrorDetails, driven by APP_DEBUG=1) the full
 *     message + trace is ALSO shown in-browser; in production it is only logged.
 *
 * This closes P2-SEC-007 (info disclosure): the raw mysqli_error() string that
 * the retired `or die(mysqli_error())` idiom used to print never reaches the
 * client.
 *
 * The __invoke signature matches Slim's error-handler contract.
 */
final class ErrorHandler
{
    private readonly ResponseFactoryInterface $responseFactory;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $displayErrorDetails = false,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $referenceId = bin2hex(random_bytes(4));
        $status = $this->statusFor($exception);

        // TracingMiddleware (Task 12) is the OUTERMOST middleware, wrapping
        // even ErrorMiddleware (see app.php) - so by the time an exception
        // lands here, the request already carries the root span it attached
        // before dispatch. Recording it here, rather than relying on
        // TracingMiddleware's own defensive catch, is what actually fires in
        // practice: ErrorMiddleware (INNER than Tracing) catches every real
        // exception before Tracing's process() ever sees a throw.
        $span = $request->getAttribute(TracingMiddleware::SPAN_ATTRIBUTE);
        if ($span instanceof SpanInterface) {
            $span->recordException($exception, ['reference_id' => $referenceId]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $this->logger->error($exception->getMessage(), [
            'reference_id' => $referenceId,
            'exception'    => $exception,
            'method'       => $request->getMethod(),
            'uri'          => (string) $request->getUri(),
            'status'       => $status,
        ]);

        // Either the constructor flag (APP_DEBUG) or the per-call flag Slim
        // passes down enables in-browser detail; production has both off.
        $showDetails = $this->displayErrorDetails || $displayErrorDetails;

        return $this->wantsJson($request)
            ? $this->jsonResponse($status, $referenceId, $showDetails ? $exception : null)
            : $this->htmlResponse($status, $referenceId, $showDetails ? $exception : null);
    }

    private function statusFor(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getCode();
        }
        return 500;
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        // The file:ajax/* route-name convention (config/access_policy.php,
        // consumed by AuthorizationMiddleware) already tags AJAX side doors.
        try {
            $routeName = RouteContext::fromRequest($request)->getRoute()?->getName() ?? '';
        } catch (\RuntimeException) {
            // Routing never completed (e.g. HttpNotFoundException) - no route.
            $routeName = '';
        }
        if (str_starts_with($routeName, 'file:ajax/')) {
            return true;
        }

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return true;
        }

        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private function jsonResponse(int $status, string $referenceId, ?Throwable $exception): ResponseInterface
    {
        $payload = [
            'error'        => 'An unexpected error occurred.',
            'reference_id' => $referenceId,
        ];
        if ($exception !== null) {
            $payload['detail'] = $exception->getMessage();
            $payload['trace']  = $exception->getTraceAsString();
        }

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        return $response;
    }

    private function htmlResponse(int $status, string $referenceId, ?Throwable $exception): ResponseInterface
    {
        $safeReference = htmlspecialchars($referenceId, ENT_QUOTES, 'UTF-8');
        $heading = $status === 404 ? 'Page Not Found' : 'Something Went Wrong';
        $message = $status === 404
            ? 'The page you requested could not be found.'
            : 'We hit an unexpected problem processing your request. The competition '
                . 'organizers have been notified.';

        $details = '';
        if ($exception !== null) {
            $details = "\n    <pre class=\"trace\">"
                . htmlspecialchars(
                    $exception::class . ': ' . $exception->getMessage() . "\n\n" . $exception->getTraceAsString(),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . "</pre>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error - Brew Competition Online Entry &amp; Management</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
               background: #f5f5f5; color: #333; margin: 0; padding: 0; }
        .box { max-width: 640px; margin: 10vh auto; background: #fff; border-radius: 8px;
               box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 2.5rem; }
        h1 { color: #7b1e1e; font-size: 1.6rem; margin-top: 0; }
        .ref { display: inline-block; margin-top: 1.5rem; padding: .4rem .7rem; background: #f0f0f0;
               border-radius: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .95rem; }
        .trace { margin-top: 1.5rem; padding: 1rem; background: #1e1e1e; color: #eee; border-radius: 6px;
                 overflow-x: auto; font-size: .8rem; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{$heading}</h1>
        <p>{$message}</p>
        <p>If you contact support, please quote this reference ID:</p>
        <span class="ref">{$safeReference}</span>{$details}
    </div>
</body>
</html>
HTML;

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($html);
        return $response;
    }
}
