<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

/**
 * StyleSet represents the beer style guidelines used in this competition.
 *
 * Each set defines the official beer categories and subcategories that can be entered.
 * The competition uses exactly one style set, though style exceptions can be added for local categories.
 */
enum StyleSet: string
{
    case BJCP2025 = 'BJCP2025';
    case BJCP2021 = 'BJCP2021';
    case BJCP2015 = 'BJCP2015';
    case AABC2025 = 'AABC2025';
    case AABC2022 = 'AABC2022';
    case BA = 'BA';  // British Association

    /**
     * Human-readable label for this style set.
     *
     * Example: "BJCP 2025 Guidelines"
     */
    public function label(): string
    {
        return match ($this) {
            StyleSet::BJCP2025 => 'BJCP 2025 Guidelines',
            StyleSet::BJCP2021 => 'BJCP 2021 Guidelines',
            StyleSet::BJCP2015 => 'BJCP 2015 Guidelines',
            StyleSet::AABC2025 => 'AABC 2025 Guidelines',
            StyleSet::AABC2022 => 'AABC 2022 Guidelines',
            StyleSet::BA => 'British Association Guidelines',
        };
    }

    /**
     * Whether this style set is the current active standard.
     *
     * Generally, only the most recent BJCP or AABC version should return true.
     */
    public function isActive(): bool
    {
        return match ($this) {
            StyleSet::BJCP2025 => true,
            StyleSet::BJCP2021 => false,
            StyleSet::BJCP2015 => false,
            StyleSet::AABC2025 => true,
            StyleSet::AABC2022 => false,
            StyleSet::BA => false,
        };
    }

    /**
     * Approximate number of official styles in this style set.
     *
     * Used for validation and UI display. This is a rough count of the main categories.
     * Subcategories may add more detail.
     */
    public function supportedStyles(): int
    {
        return match ($this) {
            StyleSet::BJCP2025 => 32,
            StyleSet::BJCP2021 => 32,
            StyleSet::BJCP2015 => 30,
            StyleSet::AABC2025 => 24,
            StyleSet::AABC2022 => 24,
            StyleSet::BA => 20,
        };
    }

    /**
     * Description of this style set for admin documentation.
     */
    public function description(): string
    {
        return match ($this) {
            StyleSet::BJCP2025 => 'Beer Judge Certification Program 2025 Guidelines - Current standard',
            StyleSet::BJCP2021 => 'Beer Judge Certification Program 2021 Guidelines - Previous version',
            StyleSet::BJCP2015 => 'Beer Judge Certification Program 2015 Guidelines - Older version',
            StyleSet::AABC2025 => 'Australian Association of Brewers and Cider Makers 2025 - Current standard',
            StyleSet::AABC2022 => 'Australian Association of Brewers and Cider Makers 2022 - Previous version',
            StyleSet::BA => 'British Association of Brewers - Historic guidelines',
        };
    }
}
