<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

final class TableId
{
    public function __construct(private readonly int $value)
    {
        // 0 is a legitimate sentinel for "not yet persisted" (see
        // JudgingTableService::createTable(), which builds a JudgingTable
        // before the repository has assigned a real auto-increment id) -
        // mirrors EntryId's identical convention.
        if ($value < 0) {
            throw new \InvalidArgumentException('TableId cannot be negative');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(TableId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
