<?php
/**
 * Module:        docker/config.php
 * Description:   Docker-specific override of site/config.php. Bind-mounted over
 *                 site/config.php by docker-compose.yml. Reads connection info
 *                 from environment variables set in docker-compose.yml instead
 *                 of hardcoding credentials.
 */

$hostname = getenv('DB_HOST') ?: 'db';
$username = getenv('DB_USER') ?: 'bcoem';
$password = getenv('DB_PASSWORD') ?: 'bcoem_password';
$database = getenv('DB_NAME') ?: 'bcoem';
// Cast to int (Task 12 review fix): ini_get() always returns a string, and
// passing that straight to `new mysqli(...)` broke OpenTelemetry's mysqli
// auto-instrumentation - MySqliTracker::storeMySqliAttributes()'s strict
// `?int $port` parameter type-errors on a string port, and that TypeError is
// thrown from INSIDE the extension's post-hook callback, aborting
// constructPostHook() before it reaches endSpan(). The scope this connect's
// span pushed onto OTel's ambient Context stack is then never detached -
// every mysqli_query() span for the rest of the request nests under that
// leaked, never-exported "ghost" span instead of directly under
// TracingMiddleware's root span. See task-12-report.md's "Review fix round
// 1" section for the full investigation.
$database_port = (int) ini_get('mysqli.default_port');

/**
 * Reuse an existing connection if one is already set (the PHPUnit
 * IntegrationTestCase injects its transactional connection via
 * $GLOBALS['connection'] so library code shares the test's uncommitted
 * state). Web requests never have one pre-set and connect normally.
 */
if (isset($GLOBALS['connection']) && $GLOBALS['connection'] instanceof mysqli) {
    $connection = $GLOBALS['connection'];
} else {
    $connection = new mysqli($hostname, $username, $password, $database, $database_port);
    mysqli_set_charset($connection,'utf8mb4');
    mysqli_query($connection, "SET NAMES 'utf8mb4';");
    mysqli_query($connection, "SET CHARACTER SET 'utf8mb4';");
    mysqli_query($connection, "SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';");
    mysqli_query($connection, "SET sql_mode = '';");
}

$brewing = $connection;

/**
 * Matches the table prefix used in sql/bcoem_baseline_3.0.X.sql.
 */
$prefix = 'baseline_';

$installation_id = 'docker-local';
$session_expire_after = 30;

/**
 * Controlled by docker-compose.install.yml (SETUP_FREE_ACCESS=true) so the
 * setup wizard can be reached for a fresh install without editing this file
 * by hand. Defaults to closed.
 */
$setup_free_access = (getenv('SETUP_FREE_ACCESS') === 'true');

$sub_directory = '';

/**
 * SERVER_NAME reflects Apache's internal name for itself (port 80 inside
 * the container), not the host port docker-compose.yml maps it to
 * (8080). HTTP_HOST instead mirrors the client's actual Host header, so
 * it keeps whatever port the browser really connected through.
 */
$base_url = 'http://';
if (is_https()) $base_url = 'https://';
$base_url .= $_SERVER['HTTP_HOST'].$sub_directory.'/';

$server_root = $_SERVER['DOCUMENT_ROOT'];
?>
