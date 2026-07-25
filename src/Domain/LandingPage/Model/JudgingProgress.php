<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class JudgingProgress
{
    public function __construct(
        public bool $started,
        public bool $ended,
        public bool $displayWinners,
        public int $winnerReleaseAt,
    ) {
    }
}
