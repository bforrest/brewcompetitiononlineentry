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

// This host runs with output_buffering off, so PHP sends headers - locking
// in whatever HTTP status is current - the moment the FIRST byte of body is
// echoed. Several branches below only learn the true outcome (e.g. the
// migration's exit code) after already having echoed the banner and/or the
// command's own output. Without an explicit buffer, a later
// http_response_code(500) call would silently no-op (headers already sent)
// and the client would still see 200. Buffering here defers the actual
// header flush until PHP's normal shutdown sequence (which happens even on
// exit()), so the LAST http_response_code() call made anywhere below - not
// the first echo - decides the status the client actually receives.
ob_start();

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
// The trailing `echo "EXIT_CODE:$?"` runs in the same shell invocation as
// the migrate command (shell_exec() spawns a single `sh -c '...'`), so `$?`
// still reflects the migrate command's own exit status, not echo's. This
// lets us recover the real exit code from shell_exec()'s string return
// value, which otherwise only tells us "no output" vs "some output" - not
// success vs failure. See Task 13 review finding: a non-zero exit with
// output (the common failure shape, e.g. bad SQL or a dropped DB
// connection mid-run) must NOT fall through to "Done." with an implicit
// HTTP 200.
$command = sprintf(
    '%s %s migrate -c %s 2>&1; echo "EXIT_CODE:$?"',
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

$exitCode = null;
if (preg_match('/EXIT_CODE:(-?\d+)\s*\z/', $output, $matches)) {
    $exitCode = (int) $matches[1];
    $output = (string) substr($output, 0, -strlen($matches[0]));
}

echo rtrim($output) . "\n";
echo str_repeat('-', 40) . "\n";

if ($exitCode === null) {
    http_response_code(500);
    echo "FAILED: could not determine the migration command's exit code.\n";
    echo "Treat this as a failed run and re-check manually.\n";
    exit;
}

if ($exitCode !== 0) {
    http_response_code(500);
    echo "FAILED: migration command exited with status {$exitCode}.\n";
    echo "See the output above for details.\n";
    exit;
}

echo "Done.\n";
