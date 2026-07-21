<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

/**
 * JudgingConfiguration represents the settings for how judging will be conducted.
 *
 * Properties:
 * - isQueued: Whether to use queued judging (vs. direct-to-judge assignment)
 * - maxFlightEntries: Maximum entries per flight (e.g., 12)
 * - maxBosPerStyle: Maximum BOS places per style (e.g., 7)
 * - maxRounds: Maximum number of rounds per table (e.g., 3)
 *
 * Validation rules:
 * - All numeric values >= 1 and <= 999
 * - maxFlightEntries typically 8-20
 * - maxBosPerStyle typically 1-10
 * - maxRounds typically 1-5
 */
final class JudgingConfiguration
{
    /**
     * @param bool $isQueued Whether to use queued judging workflow
     * @param int $maxFlightEntries Maximum entries per flight (default 12)
     * @param int $maxBosPerStyle Maximum BOS places per style (default 7)
     * @param int $maxRounds Maximum rounds per table (default 3)
     * @throws InvalidConstraintException if any value is invalid
     */
    public function __construct(
        private readonly bool $isQueued = true,
        private readonly int $maxFlightEntries = 12,
        private readonly int $maxBosPerStyle = 7,
        private readonly int $maxRounds = 3,
    ) {
        $this->validate();
    }

    /**
     * Validate the judging configuration for business rule violations.
     *
     * @throws InvalidConstraintException
     */
    private function validate(): void
    {
        if ($this->maxFlightEntries < 1 || $this->maxFlightEntries > 999) {
            throw new InvalidConstraintException(
                sprintf('Max flight entries must be between 1 and 999, got %d', $this->maxFlightEntries)
            );
        }

        if ($this->maxBosPerStyle < 1 || $this->maxBosPerStyle > 999) {
            throw new InvalidConstraintException(
                sprintf('Max BOS per style must be between 1 and 999, got %d', $this->maxBosPerStyle)
            );
        }

        if ($this->maxRounds < 1 || $this->maxRounds > 999) {
            throw new InvalidConstraintException(
                sprintf('Max rounds must be between 1 and 999, got %d', $this->maxRounds)
            );
        }

        // Typical range checks for warnings (but still allow outside ranges)
        if ($this->maxFlightEntries < 8 || $this->maxFlightEntries > 20) {
            // This is just informational; we still allow it
            // Log: "maxFlightEntries {$this->maxFlightEntries} is outside typical range 8-20"
        }

        if ($this->maxBosPerStyle < 1 || $this->maxBosPerStyle > 10) {
            // Log: "maxBosPerStyle {$this->maxBosPerStyle} is outside typical range 1-10"
        }

        if ($this->maxRounds < 1 || $this->maxRounds > 5) {
            // Log: "maxRounds {$this->maxRounds} is outside typical range 1-5"
        }
    }

    public function isQueued(): bool
    {
        return $this->isQueued;
    }

    public function maxFlightEntries(): int
    {
        return $this->maxFlightEntries;
    }

    public function maxBosPerStyle(): int
    {
        return $this->maxBosPerStyle;
    }

    public function maxRounds(): int
    {
        return $this->maxRounds;
    }

    /**
     * Check if a flight size is valid (doesn't exceed max).
     *
     * @param int $count Number of entries in the flight
     * @return bool True if flight size is within limits
     */
    public function validateFlightSize(int $count): bool
    {
        if ($count < 0) {
            return false;
        }

        return $count <= $this->maxFlightEntries;
    }

    /**
     * Create a new JudgingConfiguration with updated max flight entries (copy-on-write).
     *
     * @throws InvalidConstraintException if value is invalid
     */
    public function withMaxFlightEntries(int $value): self
    {
        if ($value === $this->maxFlightEntries) {
            return $this;
        }

        return new self($this->isQueued, $value, $this->maxBosPerStyle, $this->maxRounds);
    }

    /**
     * Create a new JudgingConfiguration with updated max BOS per style (copy-on-write).
     *
     * @throws InvalidConstraintException if value is invalid
     */
    public function withMaxBosPerStyle(int $value): self
    {
        if ($value === $this->maxBosPerStyle) {
            return $this;
        }

        return new self($this->isQueued, $this->maxFlightEntries, $value, $this->maxRounds);
    }

    /**
     * Create a new JudgingConfiguration with updated max rounds (copy-on-write).
     *
     * @throws InvalidConstraintException if value is invalid
     */
    public function withMaxRounds(int $value): self
    {
        if ($value === $this->maxRounds) {
            return $this;
        }

        return new self($this->isQueued, $this->maxFlightEntries, $this->maxBosPerStyle, $value);
    }

    /**
     * Create a new JudgingConfiguration with updated queued mode (copy-on-write).
     */
    public function withIsQueued(bool $value): self
    {
        if ($value === $this->isQueued) {
            return $this;
        }

        return new self($value, $this->maxFlightEntries, $this->maxBosPerStyle, $this->maxRounds);
    }
}
