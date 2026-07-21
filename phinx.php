<?php

declare(strict_types=1);

/**
 * Phinx configuration (Task 13).
 *
 * Deliberately reads its DB connection info from site/config.php rather than
 * duplicating its own getenv() fallback chain - site/config.php is the
 * single source of truth for deploy-varying config (Task 13, Part 2) on
 * both installation types this app supports:
 *   - Docker: site/config.php resolves $hostname/$username/$password/
 *     $database/$prefix from the real environment variables docker-compose.yml
 *     sets (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PREFIX).
 *   - Shared hosting: no environment variables are ever set there, so
 *     site/config.php resolves those same variables from whatever the
 *     admin hand-edited into that file - the only place real credentials
 *     exist on that install type. Requiring site/config.php here (the same
 *     file the app itself boots from) is what lets file:phinx-migrate.php
 *     (see that file + config/access_policy.php) connect with the exact
 *     same credentials the running site uses, with zero separate
 *     configuration step.
 *
 * A real mysqli connection ($connection) also gets opened as a side effect
 * of requiring site/config.php - Phinx opens its own separate PDO
 * connection immediately after via the 'mysql' adapter below and never
 * touches $connection. This mirrors an existing, established pattern in
 * this codebase: lib/update.lib.php's check_setup()/check_update() also
 * `require(CONFIG.'config.php')` purely to read config variables, each
 * paying the same one-time connection-open cost. Acceptable here since
 * migrations are an infrequent, one-off admin action, not a hot path.
 */

define('ROOT', __DIR__ . DIRECTORY_SEPARATOR);
define('CONFIG', ROOT . 'site' . DIRECTORY_SEPARATOR);

// config.php calls is_https() (defined in paths.php) to build $base_url.
// Phinx never uses $base_url, but PHP still resolves the whole file, so the
// function must exist - defined here rather than pulling in the rest of
// paths.php (session_start(), current_version.inc.php, etc.), none of
// which config.php or Phinx need. Mirrors tests/bootstrap.php's own
// same-shaped stub for sterilize() for the same reason.
if (!function_exists('is_https')) {
    function is_https() {
        if (((!empty($_SERVER['HTTPS'])) && (strtolower($_SERVER['HTTPS']) !== "off")) || ((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] === "443"))) return true;
        elseif (((!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https")) || ((!empty($_SERVER['HTTP_X_FORWARDED_SSL'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) == "on"))) return true;
        else return false;
    }
}

// config.php also reads $_SERVER['SERVER_NAME']/['DOCUMENT_ROOT'] to build
// $base_url/$server_root (neither of which Phinx uses) - both are unset in
// this CLI context, which is harmless (PHP warning only, not fatal) but
// noisy in `phinx migrate` output; filled in with inert placeholders so
// that output stays clean without changing config.php itself.
$_SERVER['SERVER_NAME'] ??= 'cli';
$_SERVER['DOCUMENT_ROOT'] ??= __DIR__;

require CONFIG . 'config.php';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        // Phinx's own bookkeeping table. Given its own name (not
        // "migrations") so it can never collide with a legacy or Phase 3
        // table of that name, and left UNPREFIXED deliberately - it tracks
        // which of THIS install's migration files have run, which is a
        // property of the schema/filesystem pairing, not of any one
        // logical "installation" sharing a physical database via $prefix.
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'bcoem',
        'bcoem' => [
            'adapter' => 'mysql',
            'host' => $hostname,
            'name' => $database,
            'user' => $username,
            'pass' => $password,
            'port' => $database_port,
            'charset' => 'utf8mb4',
            // Every table this app owns (legacy and new) is namespaced by
            // $prefix so multiple installs can share one physical database
            // (see config.php's own "DB Prefix" section) - Phinx's
            // TablePrefixAdapter applies that same prefix transparently to
            // every $this->table(...) call a migration makes, so migration
            // classes below just say 'audit_log', not '{$prefix}audit_log'.
            'table_prefix' => $prefix,
        ],
    ],
    'version_order' => 'creation',
];
