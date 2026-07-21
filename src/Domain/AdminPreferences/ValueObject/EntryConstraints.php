<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

/**
 * EntryConstraints represents the limits on how many entries brewers can submit.
 *
 * This is an immutable value object. Constraints can be applied at multiple levels:
 * 1. globalEntryLimit: Maximum entries per brewer across entire competition
 * 2. perStyleLimits: Per-style limits (e.g., max 3 IPAs per brewer)
 * 3. perTableLimit: Single limit applied per judging table (mutually exclusive with perStyleLimits)
 * 4. subCategoryLimits: Limits by category string (e.g., 'IPA' => 2)
 *
 * Validation rules:
 * - All limits must be positive integers >= 1
 * - Cannot have both perStyleLimits and perTableLimit set (mutually exclusive)
 * - globalEntryLimit >= 1
 */
final class EntryConstraints
{
    /**
     * @param int $globalEntryLimit Maximum entries per brewer (default 5)
     * @param array<int, int> $perStyleLimits Limits by style ID (e.g., [1 => 3, 2 => 5])
     * @param int|null $perTableLimit Single limit for all entries at a table (mutually exclusive with perStyleLimits)
     * @param array<string, int> $subCategoryLimits Limits by category (e.g., ['IPA' => 2])
     * @throws InvalidConstraintException if constraints are invalid
     */
    public function __construct(
        private readonly int $globalEntryLimit = 5,
        private readonly array $perStyleLimits = [],
        private readonly int|null $perTableLimit = null,
        private readonly array $subCategoryLimits = [],
    ) {
        $this->validate();
    }

    /**
     * Validate the constraints for business rule violations.
     *
     * @throws InvalidConstraintException
     */
    private function validate(): void
    {
        // Check globalEntryLimit
        if ($this->globalEntryLimit < 1) {
            throw new InvalidConstraintException(
                sprintf('Global entry limit must be >= 1, got %d', $this->globalEntryLimit)
            );
        }

        // Check perStyleLimits: all values must be positive
        foreach ($this->perStyleLimits as $styleId => $limit) {
            if (!is_int($styleId) || $styleId < 1) {
                throw new InvalidConstraintException(
                    sprintf('Style ID must be a positive integer, got %s', var_export($styleId, true))
                );
            }
            if (!is_int($limit) || $limit < 1) {
                throw new InvalidConstraintException(
                    sprintf('Per-style limit for style %d must be >= 1, got %d', $styleId, $limit)
                );
            }
        }

        // Check perTableLimit
        if ($this->perTableLimit !== null && $this->perTableLimit < 1) {
            throw new InvalidConstraintException(
                sprintf('Per-table limit must be >= 1, got %d', $this->perTableLimit)
            );
        }

        // Validate: cannot have both perStyleLimits and perTableLimit
        if (!empty($this->perStyleLimits) && $this->perTableLimit !== null) {
            throw new InvalidConstraintException(
                'Cannot have both per-style limits and per-table limit (mutually exclusive)'
            );
        }

        // Check subCategoryLimits: all values must be positive
        foreach ($this->subCategoryLimits as $category => $limit) {
            if (!is_string($category) || empty($category)) {
                throw new InvalidConstraintException(
                    sprintf('Category must be a non-empty string, got %s', var_export($category, true))
                );
            }
            if (!is_int($limit) || $limit < 1) {
                throw new InvalidConstraintException(
                    sprintf('Sub-category limit for "%s" must be >= 1, got %d', $category, $limit)
                );
            }
        }
    }

    public function globalEntryLimit(): int
    {
        return $this->globalEntryLimit;
    }

    /**
     * @return array<int, int>
     */
    public function perStyleLimits(): array
    {
        return $this->perStyleLimits;
    }

    public function perTableLimit(): int|null
    {
        return $this->perTableLimit;
    }

    /**
     * @return array<string, int>
     */
    public function subCategoryLimits(): array
    {
        return $this->subCategoryLimits;
    }

    /**
     * Check if a new entry can be submitted given current constraints.
     *
     * This is a basic validation that checks if the style/category would be allowed.
     * Full validation (checking current entry counts) happens in the service layer.
     *
     * @param int $styleId The beer style ID being entered
     * @param string|null $category The beer category (e.g., 'IPA') if applicable
     * @return bool True if entry is allowed by constraints; false if it violates limits
     */
    public function canSubmitEntry(int $styleId, string|null $category = null): bool
    {
        // Basic check: ensure we have some capacity
        // This is just a quick sanity check; full validation happens in service

        if ($this->globalEntryLimit < 1) {
            return false;
        }

        // Per-style limit check (if set)
        if (!empty($this->perStyleLimits) && isset($this->perStyleLimits[$styleId])) {
            if ($this->perStyleLimits[$styleId] < 1) {
                return false;
            }
        }

        // Per-table limit check (if set)
        if ($this->perTableLimit !== null && $this->perTableLimit < 1) {
            return false;
        }

        // Sub-category limit check (if set)
        if ($category !== null && !empty($this->subCategoryLimits)) {
            if (isset($this->subCategoryLimits[$category])) {
                if ($this->subCategoryLimits[$category] < 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a new EntryConstraints with updated global limit (copy-on-write).
     *
     * @throws InvalidConstraintException if limit is invalid
     */
    public function withGlobalLimit(int $limit): self
    {
        if ($limit === $this->globalEntryLimit) {
            return $this;
        }

        return new self($limit, $this->perStyleLimits, $this->perTableLimit, $this->subCategoryLimits);
    }

    /**
     * Create a new EntryConstraints with updated per-style limits (copy-on-write).
     *
     * @param array<int, int> $limits Style ID => limit pairs
     * @throws InvalidConstraintException if limits are invalid or conflicting
     */
    public function withPerStyleLimits(array $limits): self
    {
        if ($limits === $this->perStyleLimits) {
            return $this;
        }

        return new self($this->globalEntryLimit, $limits, null, $this->subCategoryLimits);
    }

    /**
     * Create a new EntryConstraints with updated per-table limit (copy-on-write).
     *
     * @throws InvalidConstraintException if limit is invalid or conflicts with per-style limits
     */
    public function withPerTableLimit(int|null $limit): self
    {
        if ($limit === $this->perTableLimit) {
            return $this;
        }

        return new self($this->globalEntryLimit, [], $limit, $this->subCategoryLimits);
    }

    /**
     * Create a new EntryConstraints with updated sub-category limits (copy-on-write).
     *
     * @param array<string, int> $limits Category => limit pairs
     * @throws InvalidConstraintException if limits are invalid
     */
    public function withSubCategoryLimits(array $limits): self
    {
        if ($limits === $this->subCategoryLimits) {
            return $this;
        }

        return new self($this->globalEntryLimit, $this->perStyleLimits, $this->perTableLimit, $limits);
    }
}
