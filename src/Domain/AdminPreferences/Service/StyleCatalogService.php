<?php

declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Database\Connection;

/**
 * StyleCatalogService provides access to available beer styles.
 *
 * Responsibilities:
 * - Load beer styles from database
 * - Query styles by category/subcategory
 * - Validate style selections
 */
final class StyleCatalogService
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Get all available styles.
     *
     * @return array<int, array<string, mixed>> Array of styles
     */
    public function getAllStyles(): array
    {
        // TODO: Implement in Task 3
        return [];
    }

    /**
     * Get style by ID.
     *
     * @param int $styleId Style ID
     * @return array<string, mixed>|null Style data or null if not found
     */
    public function getStyleById(int $styleId): ?array
    {
        // TODO: Implement in Task 3
        return null;
    }

    /**
     * Get styles by category.
     *
     * @param string $category Style category
     * @return array<int, array<string, mixed>> Array of styles in category
     */
    public function getStylesByCategory(string $category): array
    {
        // TODO: Implement in Task 3
        return [];
    }
}
