<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class WinnerSummary
{
    public function __construct(
        public int $method,
        public int $styleSet,
        public int $scoredEntryCount,
    ) {
    }
}
