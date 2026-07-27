<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class BestOfShowSummary
{
    /** @param list<BestOfShowWinner> $rows */
    public function __construct(public array $rows)
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('Best of Show rows must be a list.');
        }
        foreach ($rows as $row) {
            if (!$row instanceof BestOfShowWinner) {
                throw new \InvalidArgumentException('Best of Show rows must contain only BestOfShowWinner values.');
            }
        }
    }
}
