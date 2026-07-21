<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\ValueObject;

/**
 * Immutable, typed identifier for a Brewer (users table row).
 */
final class BrewerId
{
    public function __construct(private int $id)
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('BrewerId must be a positive integer');
        }
    }

    public static function from(int $id): self
    {
        return new self($id);
    }

    public function value(): int
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
