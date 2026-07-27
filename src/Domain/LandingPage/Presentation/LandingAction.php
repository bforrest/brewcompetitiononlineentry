<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class LandingAction
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
        SafeUrl::assert($url);
    }
}
