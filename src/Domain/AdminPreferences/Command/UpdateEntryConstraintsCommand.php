<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * UpdateEntryConstraintsCommand is the input DTO for updating entry limits.
 *
 * Properties:
 * - globalEntryLimit: Maximum entries per brewer across entire competition (1-999)
 * - perStyleLimits: Optional limits by style ID (e.g., [1 => 3, 2 => 5])
 * - perTableLimit: Optional single limit applied per judging table (mutually exclusive with perStyleLimits)
 * - subCategoryLimits: Optional limits by category string (e.g., ['IPA' => 2])
 */
final class UpdateEntryConstraintsCommand
{
    #[Assert\Type(type: 'integer')]
    #[Assert\Range(min: 1, max: 999)]
    public int $globalEntryLimit;

    /**
     * @var array<int|string, int>
     */
    #[Assert\Type(type: 'array')]
    public array $perStyleLimits = [];

    #[Assert\Type(type: 'integer')]
    #[Assert\Range(min: 1, max: 999)]
    public ?int $perTableLimit = null;

    /**
     * @var array<string, int>
     */
    #[Assert\Type(type: 'array')]
    public array $subCategoryLimits = [];

    /**
     * @param array<int|string, int> $perStyleLimits
     * @param array<string, int> $subCategoryLimits
     */
    public function __construct(
        int $globalEntryLimit,
        array $perStyleLimits = [],
        ?int $perTableLimit = null,
        array $subCategoryLimits = []
    ) {
        $this->globalEntryLimit = $globalEntryLimit;
        $this->perStyleLimits = $perStyleLimits;
        $this->perTableLimit = $perTableLimit;
        $this->subCategoryLimits = $subCategoryLimits;
    }
}
