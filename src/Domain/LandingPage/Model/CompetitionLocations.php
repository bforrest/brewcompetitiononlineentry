<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionLocations
{
    public function __construct(
        public ?string $shippingName,
        public ?string $shippingAddress,
        public ?string $awardsDetails,
        public ?string $awardsLocationName,
        public ?string $awardsLocation,
        public ?int $awardsAt,
    ) {
    }
}
