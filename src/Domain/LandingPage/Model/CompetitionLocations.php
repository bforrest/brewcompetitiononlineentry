<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionLocations
{
    /** @param list<DropoffLocation> $dropoffLocations */
    public function __construct(
        public ?string $shippingName,
        public ?string $shippingAddress,
        public ?string $awardsDetails,
        public ?string $awardsLocationName,
        public ?string $awardsLocation,
        public ?int $awardsAt,
        public bool $shippingEnabled = false,
        public array $dropoffLocations = [],
    ) {
        if (!array_is_list($dropoffLocations)) {
            throw new \InvalidArgumentException('Drop-off locations must be a list.');
        }
        foreach ($dropoffLocations as $location) {
            if (!$location instanceof DropoffLocation) {
                throw new \InvalidArgumentException('Drop-off locations must contain only DropoffLocation values.');
            }
        }
    }
}
