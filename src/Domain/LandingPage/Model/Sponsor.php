<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class Sponsor
{
    public function __construct(
        public string $name,
        public ?string $websiteUrl,
        public ?string $imagePath,
        public ?string $description,
        public ?string $location,
        public int $level,
    ) {
        SafeUrl::assert($websiteUrl);
        SafeUrl::assert($imagePath);
    }
}
