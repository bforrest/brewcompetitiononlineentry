<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Deliberately NOT `require_once __DIR__ . '/paths.php'` here, despite that
 * being this file's originally planned shape (Task 9 brief Step 2) - doing
 * so causes a real, confirmed-by-curl bug: every route 302-redirects to
 * setup.php?section=step0 because lib/preflight.lib.php's check_setup()
 * calls silently check table '' instead of 'baseline_mods' etc.
 *
 * Root cause: PHP's require executes the required file's top-level code in
 * the scope of the *line* that triggered it, not a universal global scope.
 * Every Bcoem\Legacy\* handler (LegacyPageHandler, LegacyProcessHandler,
 * LegacyFileHandler, LegacyBootstrap) requires its target legacy file (e.g.
 * legacy/index.php, qr.php) from WITHIN A METHOD - a function scope. Each
 * target file's own first line is require_once('paths.php'), which sets
 * $prefix/$database/$connection/etc as plain variables in whatever scope
 * that line runs in - normally the handler method's local scope, which is
 * exactly where the target file's *own* subsequent code (needing those
 * same variables) also runs, so it all lines up correctly. But if paths.php
 * had ALREADY been require_once'd once before - e.g. eagerly, right here,
 * at this file's true top-level/global scope - PHP's require_once sees it's
 * already loaded and no-ops on every later call, silently skipping
 * execution rather than re-running it in the new (method-local) scope. The
 * target file then proceeds using $prefix/$database as if they'd just been
 * set, but they're actually stuck as unrelated globals from this file's
 * scope, invisible inside the handler method without an explicit `global`
 * declaration neither this file nor any Legacy* class makes.
 *
 * Only the ROOT constant is needed before Slim dispatches to a matched
 * route handler - every Legacy* handler builds its target file's full path
 * as ROOT . $file before requiring it, so ROOT must already be defined by
 * then. Nothing else paths.php defines (LIB, CONFIG, the DB connection,
 * $prefix, the session, ...) is needed pre-dispatch: buildApp() and its
 * middleware pipeline (session/auth/authorization) don't reference any of
 * it, confirmed by grep. Defining ROOT to match paths.php's own formula
 * exactly (mirrors paths.php:13) lets paths.php's own require_once, when
 * a dispatched handler triggers it for the first and only time, run in the
 * correct scope and set everything else up exactly as before Task 9.
 */
if (!defined('ROOT')) {
    define('ROOT', __DIR__ . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/src/Kernel/app.php';

buildApp()->run();
