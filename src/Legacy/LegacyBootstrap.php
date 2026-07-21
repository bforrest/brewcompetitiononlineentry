<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

/**
 * Legacy pages assume they're being run FROM the repo root with paths.php
 * already required and $_GET populated. This class's only job is to make
 * that assumption true when a Slim route (not a direct file hit) is what's
 * actually serving the request. Throwaway - deleted page by page as Phase 3
 * migrates each workflow into src/Domain/.
 */
final class LegacyBootstrap
{
    public static function requireRootFile(string $filename): void
    {
        chdir(ROOT);
        require ROOT . $filename;
    }
}
