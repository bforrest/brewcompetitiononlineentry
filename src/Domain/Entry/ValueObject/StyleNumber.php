<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\ValueObject;

/**
 * Immutable style number formatter. Encapsulates the entry's style category and subcategory
 * as a single value object with formatting capability.
 *
 * Delegates to legacy style_number_const() temporarily during Phase 3 migration.
 * TODO: replace with pure logic once styles are extracted.
 */
final class StyleNumber
{
    public function __construct(
        private string $brewStyleGroup,
        private string $brewStyleNum,
    ) {
        if (empty($brewStyleGroup) || empty($brewStyleNum)) {
            throw new \InvalidArgumentException('StyleNumber requires both group and num');
        }
    }

    public static function from(string $group, string $num): self
    {
        return new self($group, $num);
    }

    public function group(): string
    {
        return $this->brewStyleGroup;
    }

    public function num(): string
    {
        return $this->brewStyleNum;
    }

    public function format(string $separator = ''): string
    {
        return $this->brewStyleGroup . $separator . $this->brewStyleNum;
    }

    public function equals(self $other): bool
    {
        return $this->brewStyleGroup === $other->brewStyleGroup
            && $this->brewStyleNum === $other->brewStyleNum;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
