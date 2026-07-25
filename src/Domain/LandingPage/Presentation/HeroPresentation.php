<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class HeroPresentation
{
    public function __construct(
        public string $imageUrl,
        public string $heading,
        public string $subheading,
    ) {
        SafeUrl::assert($imageUrl);
    }
}
