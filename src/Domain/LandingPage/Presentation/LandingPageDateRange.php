<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageDateRange
{
    public function __construct(
        public ?string $opens,
        public ?string $closes,
        public ?int $opensAt,
        public ?int $closesAt,
    ) {
    }
}
