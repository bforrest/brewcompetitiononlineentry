<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionWindows
{
    public function __construct(
        public int $registrationOpensAt,
        public int $registrationClosesAt,
        public int $entryOpensAt,
        public int $entryClosesAt,
        public int $judgeOpensAt,
        public int $judgeClosesAt,
        public ?int $dropoffOpensAt,
        public ?int $dropoffClosesAt,
        public ?int $shippingOpensAt,
        public ?int $shippingClosesAt,
    ) {
    }
}
