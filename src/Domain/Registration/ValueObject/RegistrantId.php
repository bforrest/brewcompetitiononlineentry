<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\ValueObject;

/**
 * Typed identifier for a newly-created users.id / brewer.uid row. Deliberately
 * scoped to this domain rather than reusing Entry\ValueObject\BrewerId, to
 * keep the two domains decoupled from each other.
 */
final class RegistrantId
{
    private function __construct(private int $id)
    {
    }

    public static function from(int $id): self
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('RegistrantId must be a positive integer');
        }
        return new self($id);
    }

    public function value(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
