<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionRules
{
    public function __construct(
        public string $competitionRules,
        public ?string $entryAcceptanceRules,
    ) {
    }
}
