<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Adapter;

/**
 * Temporary adapter for legacy lib/common.lib.php functions that are too complex
 * or coupled to reimplement immediately. During Phase 3, these are called here
 * and return typed data (not caret-delimited strings).
 *
 * As workflows migrate, these calls are replaced with real service calls.
 * Eventually this file will be deleted when all workflows are extracted.
 *
 * PHPStan rule: no direct calls to common.lib.php outside this class.
 */
final class LegacyQueryAdapter
{
    /**
     * Check if a brewer has hit their subcategory entry limit.
     * Wraps lib/common.lib.php's limit_subcategory() function.
     *
     * @return bool true if limit reached, false otherwise
     */
    public static function limitSubcategory(
        int $brewerId,
        string $styleNumber,
        int $maxPerBrewerPerStyle,
    ): bool {
        global $prefix;
        global $db_conn;

        if (!function_exists('limit_subcategory')) {
            require_once __DIR__ . '/../../../../lib/common.lib.php';
        }

        // legacy function signature: limit_subcategory($style, $pref_num, $pref_exception_sub_num, $pref_exception_sub_array, $uid)
        // For now, pass empty exception array since Phase 3.1 doesn't use style exceptions
        return (bool) \limit_subcategory($styleNumber, $maxPerBrewerPerStyle, 0, '', $brewerId);
    }

    /**
     * Get the maximum number of entries a brewer can submit.
     * Wraps lib/common.lib.php's brewer_limits() or similar.
     *
     * @return int max entry limit
     */
    public static function brewerLimits(): int
    {
        // For Phase 3.1, read directly from session prefs
        // Once registration workflow migrates, this logic moves to EntryLimitService
        return (int) ($_SESSION['prefsUserEntryLimit'] ?? 5);
    }

    /**
     * Get the global entry limit for the competition.
     *
     * @return ?int max total entries, or null for unlimited
     */
    public static function entryLimits(): ?int
    {
        return isset($_SESSION['prefsEntryLimit']) && $_SESSION['prefsEntryLimit'] > 0
            ? (int) $_SESSION['prefsEntryLimit']
            : null;
    }
}
