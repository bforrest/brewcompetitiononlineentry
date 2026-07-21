<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Service;

/**
 * Service for style-related queries and formatting.
 * Delegates to legacy code temporarily; later extracts styles into proper service.
 */
final class StyleService
{
    public function __construct()
    {
    }

    /**
     * Check if a style is available (not at limit).
     */
    public function isStyleAvailable(string $styleNumber): bool
    {
        // For now, read from session or legacy code
        // TODO: once styles are extracted, query the styles table directly
        global $prefix;
        global $db_conn;

        // Query styles table to check if style exists and is not at limit
        $query = 'SELECT brewStyleAtLimit FROM ' . $prefix . 'styles WHERE brewStyleGroup = ?';
        // Use mysqli directly here temporarily (no prepared statement available in legacy)
        // This will be replaced once StyleRepository exists

        return true; // placeholder - would query $db_conn->query($query) normally
    }
}
