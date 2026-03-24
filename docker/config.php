<?php
/**
 * Docker-specific config.php
 * Reads DB credentials from environment variables set in docker-compose.yml.
 * This file is bind-mounted into the container at site/config.php.
 */

$hostname         = getenv('DB_HOST')     ?: 'db';
$username         = getenv('DB_USER')     ?: 'bcoem';
$password         = getenv('DB_PASSWORD') ?: 'bcoem_password';
$database         = getenv('DB_NAME')     ?: 'bcoem';
$database_port    = (int)(getenv('DB_PORT') ?: 3306);

$connection = new mysqli($hostname, $username, $password, $database, $database_port);
mysqli_set_charset($connection, 'utf8mb4');
mysqli_query($connection, "SET NAMES 'utf8mb4';");
mysqli_query($connection, "SET CHARACTER SET 'utf8mb4';");
mysqli_query($connection, "SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';");
mysqli_query($connection, "SET sql_mode = '';");

$brewing = $connection;

$prefix          = '';
$installation_id = 'docker';
$session_expire_after = 30;
$setup_free_access = FALSE;
$sub_directory   = '';

$base_url = 'http://';
if (is_https()) $base_url = 'https://';
$base_url .= $_SERVER['SERVER_NAME'] . $sub_directory . '/';

$server_root = $_SERVER['DOCUMENT_ROOT'];
