<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageDates
{
    public function __construct(
        public LandingPageDateRange $registration,
        public LandingPageDateRange $entries,
        public LandingPageDateRange $judges,
        public LandingPageDateRange $dropoff,
        public LandingPageDateRange $shipping,
        public ?string $awards,
    ) {
    }
}
