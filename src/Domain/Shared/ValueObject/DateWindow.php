<?php

declare(strict_types=1);

namespace Bcoem\Domain\Shared\ValueObject;

final readonly class DateWindow
{
    public function __construct(
        private int $opensAt,
        private int $closesAt,
    ) {
        if ($closesAt < $opensAt) {
            throw new \InvalidArgumentException('Window closing time must not precede opening time.');
        }
    }

    public function statusAt(int $timestamp): WindowStatus
    {
        // This is a half-open interval: [opensAt, closesAt). In particular,
        // an exact closing timestamp is Closed. That deliberately fixes the
        // narrow one-instant legacy edge case that left it in numeric state 0.
        if ($timestamp < $this->opensAt) {
            return WindowStatus::Upcoming;
        }

        if ($timestamp < $this->closesAt) {
            return WindowStatus::Open;
        }

        return WindowStatus::Closed;
    }

    public function isOpenAt(int $timestamp): bool
    {
        return $this->statusAt($timestamp) === WindowStatus::Open;
    }
}
