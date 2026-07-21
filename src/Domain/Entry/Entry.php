<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry;

use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\BrewerInfo;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;

/**
 * Aggregate root for the Entry domain. Immutable value object representing
 * a brewing competition entry.
 *
 * Embodies core entry concepts: identity, ownership, style, confirmation status.
 * Does NOT contain business logic for validation/limits (that lives in services).
 */
final class Entry
{
    public function __construct(
        private EntryId $id,
        private BrewerId $brewerId,
        private StyleNumber $style,
        private string $name,
        private BrewerInfo $brewer,
        private bool $confirmed,
        private bool $paid,
        private bool $received,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function id(): EntryId
    {
        return $this->id;
    }

    public function brewerId(): BrewerId
    {
        return $this->brewerId;
    }

    public function style(): StyleNumber
    {
        return $this->style;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function brewer(): BrewerInfo
    {
        return $this->brewer;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function isReceived(): bool
    {
        return $this->received;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isMutable(bool $windowOpen): bool
    {
        // Entry is mutable if window is open AND entry hasn't been judged
        return $windowOpen && !$this->confirmed;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'brewerId' => $this->brewerId->value(),
            'style' => $this->style->format(),
            'name' => $this->name,
            'brewer' => $this->brewer->toArray(),
            'confirmed' => $this->confirmed,
            'paid' => $this->paid,
            'received' => $this->received,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
