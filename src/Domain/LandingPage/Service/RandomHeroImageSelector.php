<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

final class RandomHeroImageSelector implements HeroImageSelector
{
    public function select(array $candidates): string
    {
        return $candidates[random_int(0, count($candidates) - 1)];
    }
}
