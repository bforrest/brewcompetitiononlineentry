<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class DropoffLocation
{
    public function __construct(
        public string $name,
        public string $address,
        public ?string $phone,
        public ?string $websiteUrl,
        public ?string $notes,
    ) {
        SafeUrl::assert($websiteUrl);
    }
}
