<?php
/**
 * Module:        config.php
 * Description:   Database connection and site configuration.
 *
 * Credentials are read from environment variables when available (Docker, CI,
 * integration-test environments) and fall back to the hardcoded values below.
 * Set these env vars instead of editing this file for Docker / test use:
 *
 *   BCOEM_DB_HOST      (default: localhost)
 *   BCOEM_DB_USER      (default: '')
 *   BCOEM_DB_PASSWORD  (default: '')
 *   BCOEM_DB_NAME      (default: '')
 *   BCOEM_DB_PORT      (default: PHP ini mysqli.default_port or 3306)
 *   BCOEM_DB_PREFIX    (default: '')
 */

// ── DB credentials ────────────────────────────────────────────
$hostname      = getenv('BCOEM_DB_HOST')     ?: 'localhost';
$username      = getenv('BCOEM_DB_USER')     ?: '';
$password      = getenv('BCOEM_DB_PASSWORD') ?: '';
$database      = getenv('BCOEM_DB_NAME')     ?: '';
$database_port = (int)(getenv('BCOEM_DB_PORT') ?: ini_get('mysqli.default_port') ?: 3306);

// ── Table prefix ──────────────────────────────────────────────
// Use a table prefix if sharing a database with other apps.
// Example: $prefix = 'bcoem1_';
$prefix = getenv('BCOEM_DB_PREFIX') ?: '';

// ── Other site settings ───────────────────────────────────────
$installation_id    = getenv('BCOEM_INSTALLATION_ID') ?: '';
$session_expire_after = 30;
$setup_free_access  = (bool)(getenv('BCOEM_SETUP_FREE_ACCESS') ?: false);
$sub_directory      = '';

// ── DB connection ─────────────────────────────────────────────
// Promote these to global scope so the guard works correctly when config.php
// is require()'d from inside a function (which is how all library functions
// call it). Without this, $connection is a new local variable every time and
// the guard would always try to open a fresh connection.
global $connection, $database, $prefix, $brewing;

// Guard: only open a new connection if one doesn't already exist and is healthy.
// This prevents repeated require() calls (one per library function) from
// clobbering an existing connection — which is critical for integration-test
// transaction isolation.
if (empty($connection) || !($connection instanceof mysqli) || $connection->connect_error) {
    $connection = new mysqli($hostname, $username, $password, $database, $database_port);
    mysqli_set_charset($connection, 'utf8mb4');
    mysqli_query($connection, "SET NAMES 'utf8mb4';");
    mysqli_query($connection, "SET CHARACTER SET 'utf8mb4';");
    mysqli_query($connection, "SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';");
    mysqli_query($connection, "SET sql_mode = '';");
}

$brewing = $connection;

// ── Base URL / server root ────────────────────────────────────
$base_url = 'http://';
if (function_exists('is_https') && is_https()) $base_url = 'https://';
$base_url .= ($_SERVER['SERVER_NAME'] ?? 'localhost') . $sub_directory . '/';
$server_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
