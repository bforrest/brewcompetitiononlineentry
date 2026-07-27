<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageActions
{
    public function __construct(
        public ?LandingAction $account,
        public ?LandingAction $entry,
        public ?LandingAction $judge,
        public ?LandingAction $steward,
    ) {
    }
}
