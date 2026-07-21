<?php
/**
 * Module:        phinx-migrate.php
 * Description:   Auth-gated, browser-triggered Phinx migration runner for
 *                shared-hosting installs with no shell/SSH access to run
 *                `vendor/bin/phinx migrate` directly (Task 13, Part 1).
 *                Mirrors update.php's existing self-bootstrapping side-door
 *                pattern: a thin root-level script, wrapped behind the
 *                central authorization pipeline via
 *                config/access_policy.php's 'file:phinx-migrate.php' entry
 *                (Role::SuperAdmin - see src/Kernel/app.php's file:* route
 *                loop, which derives this file's route straight from that
 *                policy map).
 *
 * Belt-and-suspenders: the central AuthorizationMiddleware gate is the
 * single source of truth (deny-by-default, Task 2-9), but every existing
 * file:* side door in this app ALSO carries its own internal check
 * matching its own policy role (see access_policy.php's citations for
 * qr.php, send_test_email.admin.php, etc.) - this file follows the same
 * convention rather than being the one exception to it.
 */

require_once ('paths.php');

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['loginUsername']) || (int)($_SESSION['userLevel'] ?? 1) !== 0) {
    http_response_code(403);
    echo "Forbidden: this action requires a Super Admin session.\n";
    exit;
}

echo "BCOE&M - Phinx migration runner\n";
echo str_repeat('-', 40) . "\n";

if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
    http_response_code(500);
    echo "shell_exec() is disabled on this host (see php.ini's disable_functions).\n";
    echo "This runner cannot invoke Phinx without it - run the following via\n";
    echo "SSH instead:\n\n";
    echo "    php " . ROOT . "vendor/bin/phinx migrate -c " . ROOT . "phinx.php\n";
    exit;
}

$phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$command = sprintf(
    '%s %s migrate -c %s 2>&1',
    escapeshellarg($phpBinary),
    escapeshellarg(ROOT . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phinx'),
    escapeshellarg(ROOT . 'phinx.php')
);

$output = shell_exec($command);

if ($output === null) {
    http_response_code(500);
    echo "Migration command produced no output - it may have failed to start.\n";
    echo "Command run: {$command}\n";
    exit;
}

echo $output . "\n";
echo str_repeat('-', 40) . "\n";
echo "Done.\n";
