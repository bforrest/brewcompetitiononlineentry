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
 *
 * IMPORTANT — multiple-require safety
 * ────────────────────────────────────
 * Several library functions call require(CONFIG.'config.php') themselves, even
 * when the caller (e.g. style_convert) already called it at the top of the
 * function.  Because PHP's require re-executes the file every time, a naive
 * implementation would overwrite $database and $prefix with empty strings on
 * the second call, breaking the next mysqli_select_db() call.
 *
 * The fix: promote $connection, $database, $prefix, and $brewing to global
 * scope FIRST, then use the already-set global value wherever it is non-empty,
 * and fall back to the env-var / hardcoded default only when the global is
 * empty.  This way the second (and any subsequent) require() in the same
 * function scope is a no-op for the globals that the test setUp() already
 * seeded.
 */

// ── Promote to global scope BEFORE any assignments ────────────
// This must come first so that every subsequent $database = ... or
// $prefix = ... assignment writes to the global, not a fresh local.
global $connection, $database, $prefix, $brewing;

// ── DB credentials ────────────────────────────────────────────
// $hostname, $username, $password, and $database_port are always local
// (they are only used to open a new connection, which the guard below
// skips if a connection already exists).
$hostname      = getenv('BCOEM_DB_HOST')     ?: 'localhost';
$username      = getenv('BCOEM_DB_USER')     ?: '';
$password      = getenv('BCOEM_DB_PASSWORD') ?: '';
$database_port = (int)(getenv('BCOEM_DB_PORT') ?: ini_get('mysqli.default_port') ?: 3306);

// Only overwrite $database and $prefix when they haven't been set yet.
// If setUp() (or a previous require) already populated the globals,
// leave them alone — overwriting with '' would break subsequent queries.
if (empty($database)) $database = getenv('BCOEM_DB_NAME')   ?: '';
if (empty($prefix))   $prefix   = getenv('BCOEM_DB_PREFIX') ?: '';

// ── Other site settings ───────────────────────────────────────
$installation_id    = getenv('BCOEM_INSTALLATION_ID') ?: '';
$session_expire_after = 30;
$setup_free_access  = (bool)(getenv('BCOEM_SETUP_FREE_ACCESS') ?: false);
$sub_directory      = '';

// ── DB connection ─────────────────────────────────────────────
// Guard: only open a new connection if one doesn't already exist and is healthy.
// This prevents repeated require() calls (one per library function) from
// clobbering an existing connection — which is critical for integration-test
// transaction isolation and for avoiding redundant connections in production.
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
