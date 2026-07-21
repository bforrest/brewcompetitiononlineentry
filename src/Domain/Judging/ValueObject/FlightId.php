<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

final class FlightId
{
    public function __construct(private readonly int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('FlightId must be positive');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(FlightId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
