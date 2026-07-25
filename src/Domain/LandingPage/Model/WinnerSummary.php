<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class WinnerSummary
{
    /** @param list<WinnerRow> $rows */
    public function __construct(
        public WinnerMethod $method,
        public string $styleSet,
        public array $rows,
    ) {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('Winner rows must be a list.');
        }
        foreach ($rows as $row) {
            if (!$row instanceof WinnerRow) {
                throw new \InvalidArgumentException('Winner rows must contain only WinnerRow values.');
            }
        }
    }
}
