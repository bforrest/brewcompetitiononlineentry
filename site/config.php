<?php
/**
 * Module:        config.php
 * Description:   This module houses configuration variables for DB connection, etc.
 * Last Modified: July 21, 2026
 *
 * Task 13: this file is the single source of truth for deploy-varying
 * configuration on EVERY installation type - shared hosting (hand-edited,
 * no environment variables ever set) and Docker (real environment
 * variables, no more bind-mounted override). Every value below follows the
 * same pattern: `getenv('X') ?: <the literal that used to be hardcoded
 * here>`. On a shared-hosting install where no env var is ever set,
 * getenv() returns false for every one of these keys and every line below
 * resolves to EXACTLY the literal a hand-edited install has always had -
 * zero observable behavior change for that deployment type. Docker (see
 * docker-compose.yml) sets the real env vars instead of bind-mounting a
 * second copy of this file over it (the old docker/config.php, retired by
 * this task).
 */

/**
 * ******************************************************************************
 * Set up MySQL connection variables
 * ******************************************************************************
 */

/**
 * Generally, 'localhost' will work for most environments.
 * However, some environments may require another hostname.
 * *** This has been confirmed for GO DADDY shared hosting users.
 * *** This article details how to change "localhost" to suit your Go Daddy
 *     enviornment.
 * *** https://www.godaddy.com/help/viewing-your-database-details-with-shared-hosting-accounts-39
 *
 * DB_HOST overrides this for installs that set real environment variables
 * (e.g. Docker - see docker-compose.yml). Unset on shared hosting, so the
 * hand-edited literal below is what actually takes effect there.
 */

$hostname = getenv('DB_HOST') ?: 'localhost';

/**
 * Enter the username for your database (generally the same as your login code
 * for your web hosting company).
 * INSERT YOUR USERNAME BETWEEN THE SINGLE-QUOTATION MARKS ('').
 * For example, if your username is fred then the line should read
 * $username = 'fred'.
 */


$username = getenv('DB_USER') ?: '';


/**
 * INSERT YOUR PASSWORD BETWEEN THE SINGLE-QUOTATION MARKS ('').
 * For example, if your password is flintstone then the line should read
 * $password = 'flintsone'.
 */

$password = getenv('DB_PASSWORD') ?: '';

/**
 * The following line is the name of your MySQL database you set up already.
 * If you haven't set up the database yet, please refer to
 * http://brewingcompetitions.com/install-instructions for setup instructions.
 */

$database = getenv('DB_NAME') ?: '';


/**
 * If the database port is different from the default then overwrite as the 
 * port integer
 * Example: $database_port = 3308;
 */

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
// 1" section for the full investigation. Also applies if $database_port is
// hand-edited to a numeric string like '3308' per the example above.
// DB_PORT is an additive override for installs whose MySQL runs on a
// non-default port via a real environment variable; unset on shared
// hosting, so ini_get()'s value (today's exact behavior) still wins there.
$database_port = (int) (getenv('DB_PORT') ?: ini_get('mysqli.default_port'));

/**
 * This line strings the information together and connects to MySQL.
 * If MySQL is not found or the username/password combo is not correct an
 * error will be returned.
 *
 * Reuse an existing connection if one is already set (the PHPUnit
 * IntegrationTestCase injects its transactional connection via
 * $GLOBALS['connection'] so library code shares the test's uncommitted
 * state - see tests/Integration/IntegrationTestCase.php). Web requests and
 * a real shared-hosting install never have one pre-set, so this is a
 * harmless no-op there and they always take the normal connect branch.
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

/**
 * Do not change the following line.
 */

$brewing = $connection;

/**
 * ******************************************************************************
 * End MySQL connections variables
 * ******************************************************************************
 */

/*
 * ******************************************************************************
 * DB Prefix.
 * ******************************************************************************
 * The following variable is used to define a prefix to the database tables.
 * This is useful if you wish to have separate installations or applications share
 * the same mySQL database.
 *
 * Leave as if you have a database dedicated to your BCOE&M installation.
 *
 * Suggested Usage
 * If you wish to define a prefix to the database tables, it is HIGHLY suggested
 * that you use an underscore (_), after a short descriptor that identifies which
 * install is using which tables.
 * Example:
 * $prefix = 'bcoem1_';
 * OR
 * $prefix = 'comp1_';
 *
 * DB_PREFIX overrides this for installs that set real environment
 * variables (Docker's baseline schema uses 'baseline_' - see
 * docker-compose.yml). Unset on shared hosting, so '' (today's default)
 * still wins there.
 */

$prefix = getenv('DB_PREFIX') ?: '';

/*
 * ******************************************************************************
 * Installation ID.
 * ******************************************************************************
 * Give your installation a unique ID. If you plan on running multiple instances
 * of BCOE&M from the same domain, you'll need to give each installation a
 * unique identifier. This prevents "cross-pollination" of session data display.
 *
 * For single installations, the default below will be sufficient. Otherwise,
 * change the variable to something completely unique for each installation.
 *
 * INSTALLATION_ID overrides this the same way as every other value above.
 */

$installation_id = getenv('INSTALLATION_ID') ?: '';

/*
 * ******************************************************************************
 * User session time out
 * ******************************************************************************
 * Define the time (in minutes) that a user's session will be active before it
 * expires due to inactivity. Default is 30 minutes.
 */

$session_expire_after = 30;

/*
 * ******************************************************************************
 * Access control for Setup.
 * ******************************************************************************
 * If you are going to go through the installation and setup process, you will
 * need to modify the access check statement below. Change the FALSE to a TRUE
 * to disable the access check.
 *
 * After finishing setup, be sure to open this file again and change the
 * TRUE back to a FALSE!
 *
 * SETUP_FREE_ACCESS=true overrides this for installs that set real
 * environment variables (see docker-compose.install.yml, which flips this
 * on for a fresh-install workflow without hand-editing this file). Unset
 * on shared hosting, so FALSE (today's default) still wins there.
 */

$setup_free_access = (getenv('SETUP_FREE_ACCESS') === 'true');

/*
 * ******************************************************************************
 * Set the subdirectory of your installation (if necessary).
 * ******************************************************************************
 * In most cases the default will be OK.
 *
 * IF YOU ARE RUNNING YOUR INSTANCE OF BCOE&M IN A SUBFOLDER...
 *
 * - Add the name of the subdirectory between the quotes of the $sub_directory
 *   variable.
 * - Be sure to INCLUDE a leading slash [/] and NO trailing slash [/]!
 *
 * Example:
 * $sub_directory = "/bcoem";
 *
 * WARNING!!!
 * IF you do enable the subdirectory variable, YOU MUST alter your .htaccess file
 * Otherwise, the URLs will not be generated correctly! Directions are in the
 * .htaccess file.
 */

$sub_directory = '';

/*
 * ******************************************************************************
 * Set the base URL of your installation.
 * ******************************************************************************
 * In most cases the default will be OK.
 *
 * IF you are installing on a server where you do not have a domain name set up,
 * you'll need to replace the last $base_url variable below with something
 * formatted like this:
 * $base_url .= 'yourhostingdomain/~accountname/subdirectoryname/';
 *
 * Example:
 * $base_url .= '147.21.160.5/~brewcompetition/bcoem/';
 * OR:
 * $base_url .= 'www.bluehost.com/~brewcompeition/bcoem/';
 * 
 * To override the SSL (HTTPS) check if SSL isn't implemented on your
 * server AND you're experiencing log in or session issues, or if pages are not
 * rendering correctly, comment out the second line in the block below (the if
 * statement).
 * @fixes https://github.com/geoffhumphrey/brewcompetitiononlineentry/issues/1123
 *
 * BASE_URL_USE_HTTP_HOST is an additive override for installs (Docker - see
 * docker-compose.yml) whose Apache-visible SERVER_NAME doesn't reflect the
 * port the browser actually used (SERVER_NAME comes back as just
 * "localhost" inside the container even though docker-compose.yml maps
 * that to host port 8080; HTTP_HOST mirrors the client's real Host header,
 * port included). Unset on shared hosting, so $_SERVER['SERVER_NAME']
 * (today's exact behavior) still wins there. This replaces the old
 * docker/config.php override, which hardcoded HTTP_HOST unconditionally
 * for every Docker request instead of gating it behind an env var.
 */

$base_url = 'http://';
if (is_https()) $base_url = 'https://';
$base_url_host = (getenv('BASE_URL_USE_HTTP_HOST') === 'true')
    ? ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'])
    : $_SERVER['SERVER_NAME'];
$base_url .= $base_url_host.$sub_directory.'/';

/*
 * ******************************************************************************
 * Set the server root for your installation.
 * ******************************************************************************
 * In most cases the default will be OK.
 *
 * IF you are installing on a server and will access the software via a sub-domain
 * (e.g. http://subdomain.domain.com), comment out the first variable below and
 * uncomment the second variable ONLY if you are experiencing issues. Otherwise,
 * the default will suffice.
 */

$server_root = $_SERVER['DOCUMENT_ROOT'];
//$server_root = $_SERVER['SUBDOMAIN_DOCUMENT_ROOT'];

?>