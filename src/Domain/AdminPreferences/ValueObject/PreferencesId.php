<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

/**
 * PreferencesId is a typed ID for the AdminPreferences aggregate root.
 *
 * Since there is only one row of preferences per installation (singleton pattern),
 * PreferencesId is always 1. This prevents accidental creation of multiple preference sets
 * and makes the intent clear.
 *
 * Example usage:
 *   $prefsId = new PreferencesId(1);  // OK
 *   $prefsId = new PreferencesId(2);  // Throws InvalidArgumentException
 */
final class PreferencesId
{
    /**
     * @param int $value The ID value (must be exactly 1)
     * @throws \InvalidArgumentException if value is not 1
     */
    public function __construct(private readonly int $value)
    {
        if ($value !== 1) {
            throw new \InvalidArgumentException(
                sprintf('PreferencesId must be 1 (single-row table), got %d', $value)
            );
        }
    }

    /**
     * Get the integer value of this ID.
     */
    public function value(): int
    {
        return $this->value;
    }

    /**
     * Create the canonical PreferencesId (always 1).
     */
    public static function singleton(): self
    {
        return new self(1);
    }

    /**
     * Check equality with another PreferencesId.
     */
    public function equals(PreferencesId $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * String representation (useful for logging/debugging).
     */
    public function __toString(): string
    {
        return 'PreferencesId(1)';
    }
}
