<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageSections
{
    public function __construct(
        public bool $atAGlance,
        public bool $rules,
        public bool $entryInfo,
        public bool $volunteers,
        public bool $winners,
        public bool $contact,
        public bool $sponsors,
    ) {
    }
}
