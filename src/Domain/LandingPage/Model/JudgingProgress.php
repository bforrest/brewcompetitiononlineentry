<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class JudgingProgress
{
    public bool $started;
    public bool $ended;
    public bool $displayWinners;
    public int $winnerReleaseAt;
    public bool $resultsStage;

    public function __construct(
        bool $started,
        bool $ended,
        bool $displayWinners,
        int $winnerReleaseAt,
        ?bool $resultsStage = null,
    ) {
        $this->started = $started;
        $this->ended = $ended;
        $this->displayWinners = $displayWinners;
        $this->winnerReleaseAt = $winnerReleaseAt;
        $this->resultsStage = $resultsStage ?? $ended;
    }
}
