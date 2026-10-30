<?php
/**
 * Router script for PHP's built-in server (`php -S host:port -t . router.php`).
 *
 * Task 13's shared-hosting-smoke CI job runs the packaged release zip under
 * plain `php -S` - there is no Apache/.htaccess there to funnel every
 * request through index.php the way the root .htaccess does for a real
 * shared-hosting deploy. This router mirrors that .htaccess's own rule
 * (see the root .htaccess's "Single front controller" comment): serve real
 * static assets directly, funnel everything else through index.php so the
 * Slim front controller (and its authorization pipeline) still runs.
 *
 * Not used by Docker or a real shared-hosting Apache install - both have
 * their own webserver already doing this. This file exists solely so
 * `php -S` (a dev-server stand-in, used here only to prove the packaged
 * release runs without the OTel extension) sees the same routing shape a
 * real install's webserver provides.
 *
 * Deliberately resolves index.php via $_SERVER['DOCUMENT_ROOT'] (which
 * `php -S host:port -t <docroot> <this file>` sets to <docroot>), NOT via
 * __DIR__ - this script's own location on disk is unrelated to where the
 * app being served lives (CI runs it straight from the source checkout
 * against a SEPARATELY unpacked release/ directory - see
 * .github/workflows/ci.yml's shared-hosting-smoke job), so __DIR__ would
 * resolve to the wrong tree entirely.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$assetDirs = ['css', 'js_includes', 'js_source', 'images', 'user_images', 'user_docs', 'user_temp'];
$firstSegment = trim(explode('/', ltrim($path, '/'))[0] ?? '', '/');

if (in_array($firstSegment, $assetDirs, true)) {
    return false; // let the built-in server serve the file directly
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/index.php';
