<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

interface HeroImageSelector
{
    /** @param non-empty-list<string> $candidates */
    public function select(array $candidates): string;
}
