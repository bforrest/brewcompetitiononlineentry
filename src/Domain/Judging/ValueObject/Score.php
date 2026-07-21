<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

use Bcoem\Domain\Entry\ValueObject\EntryId;

/**
 * Score represents a single score record for an entry at a judging table.
 *
 * Immutable value object containing all score-related data.
 */
final class Score
{
    public function __construct(
        private readonly int $id,
        private readonly EntryId $entryId,
        private readonly int $brewerId,
        private readonly TableId $tableId,
        private readonly float $score,
        private readonly ?string $place,
        private readonly string $scoreType,
        private readonly int $miniBos,
        private readonly int $version
    ) {
        if ($score < 0 || $score > 50) {
            throw new \InvalidArgumentException('Score must be between 0 and 50');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Version must be positive');
        }
        if (!in_array($scoreType, ['regular', 'mini-bos', 'bos'], true)) {
            throw new \InvalidArgumentException('Invalid scoreType');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function entryId(): EntryId
    {
        return $this->entryId;
    }

    public function brewerId(): int
    {
        return $this->brewerId;
    }

    public function tableId(): TableId
    {
        return $this->tableId;
    }

    public function score(): float
    {
        return $this->score;
    }

    public function place(): ?string
    {
        return $this->place;
    }

    public function scoreType(): string
    {
        return $this->scoreType;
    }

    public function miniBos(): int
    {
        return $this->miniBos;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function equals(Score $other): bool
    {
        return $this->id === $other->id
            && $this->entryId->equals($other->entryId)
            && $this->brewerId === $other->brewerId
            && $this->tableId->equals($other->tableId)
            && $this->score === $other->score
            && $this->place === $other->place
            && $this->scoreType === $other->scoreType
            && $this->miniBos === $other->miniBos
            && $this->version === $other->version;
    }
}
