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
$database_port = ini_get('mysqli.default_port');

$connection = new mysqli($hostname, $username, $password, $database, $database_port);
mysqli_set_charset($connection,'utf8mb4');
mysqli_query($connection, "SET NAMES 'utf8mb4';");
mysqli_query($connection, "SET CHARACTER SET 'utf8mb4';");
mysqli_query($connection, "SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';");
mysqli_query($connection, "SET sql_mode = '';");

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

$base_url = 'http://';
if (is_https()) $base_url = 'https://';
$base_url .= $_SERVER['SERVER_NAME'].$sub_directory.'/';

$server_root = $_SERVER['DOCUMENT_ROOT'];
?>
