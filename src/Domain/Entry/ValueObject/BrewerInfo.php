<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\ValueObject;

/**
 * Immutable brewer information value object. Hydrated from the brewer table,
 * used to enrich Entry aggregate and pass to templates.
 */
final class BrewerInfo
{
    public function __construct(
        private int $uid,
        private string $firstName,
        private string $lastName,
        private string $email,
        private ?string $breweryName = null,
        private ?string $judgeRank = null,
        private ?string $stewardRank = null,
    ) {
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            uid: (int) $row['uid'],
            firstName: (string) ($row['first_name'] ?? ''),
            lastName: (string) ($row['last_name'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            breweryName: $row['brewerName'] ?? null,
            judgeRank: $row['judgeRank'] ?? null,
            stewardRank: $row['stewardRank'] ?? null,
        );
    }

    public function uid(): int
    {
        return $this->uid;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function breweryName(): ?string
    {
        return $this->breweryName;
    }

    public function judgeRank(): ?string
    {
        return $this->judgeRank;
    }

    public function stewardRank(): ?string
    {
        return $this->stewardRank;
    }

    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->fullName(),
            'email' => $this->email,
            'breweryName' => $this->breweryName,
            'judgeRank' => $this->judgeRank,
            'stewardRank' => $this->stewardRank,
        ];
    }
}
