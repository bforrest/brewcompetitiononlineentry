<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionLimits
{
    public function __construct(
        public int $entryCount,
        public int $paidEntryCount,
        public ?int $entryLimit,
        public ?int $paidEntryLimit,
        public int $nearLimitThreshold,
    ) {
    }
}
