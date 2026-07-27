<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class BestOfShowWinner
{
    public function __construct(
        public string $groupName,
        public int $place,
        public string $brewerName,
        public ?string $coBrewerName,
        public string $entryName,
        public string $style,
        public ?string $club,
    ) {
    }
}
