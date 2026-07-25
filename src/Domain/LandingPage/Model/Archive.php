<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class Archive
{
    public function __construct(
        public string $suffix,
        public int $winnerMethod,
        public string $styleSet,
    ) {
    }
}
