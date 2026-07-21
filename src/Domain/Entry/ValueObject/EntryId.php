<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\ValueObject;

/**
 * Immutable, typed identifier for an Entry (brewing table row).
 */
final class EntryId
{
    public function __construct(private int $id)
    {
        // 0 is a legitimate sentinel for "not yet persisted" (see EntryService::create(),
        // which builds an Entry before the repository has assigned a real auto-increment id).
        if ($id < 0) {
            throw new \InvalidArgumentException('EntryId cannot be negative');
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
