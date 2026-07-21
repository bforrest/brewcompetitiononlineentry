<?php

/**
 * Integration test for the central error-handling pipeline.
 *
 * Forces a REAL mysqli failure (a query against a nonexistent table) through a
 * production-shaped Slim app - the same mysqli_sql_exception that the retired
 * `or die(mysqli_error())` idiom used to "handle" - and proves the response is
 * a clean, branded 500 (or a JSON envelope for AJAX routes) carrying only a
 * reference ID, never the raw mysqli_error() string. This is what makes
 * deleting those dead `or die()` clauses safe, and closes P2-SEC-007.
 *
 * Extends IntegrationTestCase for a live DB connection; the failing query runs
 * on that shared connection and, per InnoDB semantics, does not abort the
 * surrounding rollback-isolation transaction.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Kernel\ErrorHandler;
use DI\Bridge\Slim\Bridge;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

class ErrorHandlingTest extends IntegrationTestCase
{
    private static string $logFile;

    public static function setUpBeforeClass(): void
    {
        // Keep Monolog output out of the test console: point the container's
        // StreamHandler at a temp file (read via getenv at container build).
        self::$logFile = tempnam(sys_get_temp_dir(), 'bcoem-errlog-');
        putenv('BCOEM_LOG_FILE=' . self::$logFile);

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        putenv('BCOEM_LOG_FILE');
        if (isset(self::$logFile) && is_file(self::$logFile)) {
            @unlink(self::$logFile);
        }
    }

    /**
     * Build a Slim app wired exactly like production src/Kernel/app.php's error
     * layer, with one route that triggers a real mysqli failure.
     */
    private function buildApp(string $routeName): App
    {
        // Ensure exception-throwing mysqli mode (container.php makes this
        // explicit at boot; assert it here too so the failing query throws).
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $container = require ROOT . 'src/Kernel/container.php';
        $app = Bridge::create($container);

        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(
            new ErrorHandler($container->get('logger.app'), false)
        );

        $conn = self::$conn;
        $app->map(['GET'], '/boom', function ($request, $response) use ($conn) {
            // Nonexistent table -> mysqli_sql_exception under throwing mode.
            mysqli_query($conn, 'SELECT * FROM baseline_this_table_does_not_exist_xyz');
            return $response; // unreachable
        })->setName($routeName);

        return $app;
    }

    private function handle(App $app, string $accept = 'text/html'): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/boom')
            ->withHeader('Accept', $accept);
        return $app->handle($request);
    }

    public function test_forced_mysqli_error_returns_a_clean_branded_500(): void
    {
        $response = $this->handle($this->buildApp('section'));
        $body = (string) $response->getBody();

        $this->assertSame(500, $response->getStatusCode());
        // No raw DB diagnostics leak to the client.
        $this->assertStringNotContainsString('baseline_this_table_does_not_exist_xyz', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString("doesn't exist", $body);
        $this->assertStringNotContainsString('SELECT', $body);
        // But it is a real branded page with a reference ID.
        $this->assertStringContainsString('Something Went Wrong', $body);
        $this->assertMatchesRegularExpression('/[0-9a-f]{8}/', $body);
    }

    public function test_forced_mysqli_error_on_an_ajax_route_returns_a_json_envelope(): void
    {
        $app = $this->buildApp('file:ajax/example.ajax.php');
        $response = $this->handle($app, 'application/json');
        $body = (string) $response->getBody();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('reference_id', $decoded);
        $this->assertStringNotContainsString('baseline_this_table_does_not_exist_xyz', $body);
        $this->assertStringNotContainsString("doesn't exist", $body);
    }
}
