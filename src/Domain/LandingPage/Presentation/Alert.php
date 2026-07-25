<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class Alert
{
    public function __construct(
        public AlertLevel $level,
        public string $message,
        public ?string $linkLabel = null,
        public ?string $linkUrl = null,
    ) {
        SafeUrl::assert($linkUrl);
    }
}
