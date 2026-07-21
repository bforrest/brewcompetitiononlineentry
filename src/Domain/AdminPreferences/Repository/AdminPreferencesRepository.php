<?php

declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Repository;

use Bcoem\Database\Connection;

/**
 * AdminPreferencesRepository provides database access for admin preferences.
 *
 * Responsibilities:
 * - Load/store admin preferences from database
 * - Query preference records
 * - Update preference values
 */
final class AdminPreferencesRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Get preference value by key.
     *
     * @param string $key Preference key
     * @return mixed Preference value or null if not found
     */
    public function getByKey(string $key): mixed
    {
        // TODO: Implement in Task 3
        return null;
    }

    /**
     * Get all preferences.
     *
     * @return array<string, mixed> All preferences keyed by key
     */
    public function getAll(): array
    {
        // TODO: Implement in Task 3
        return [];
    }

    /**
     * Set preference value.
     *
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        // TODO: Implement in Task 3
    }
}
