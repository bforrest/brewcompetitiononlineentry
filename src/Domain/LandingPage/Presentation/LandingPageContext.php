<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageContext
{
    /** @param list<int> $beverageStyleTypes */
    public function __construct(
        public string $locale,
        public ?string $viewerName,
        public array $beverageStyleTypes,
    ) {
        if (!in_array($locale, ['en-US', 'en-GB', 'es-419'], true)) {
            throw new \InvalidArgumentException('Unsupported landing-page locale.');
        }
        foreach ($beverageStyleTypes as $type) {
            if ($type < 0 || $type > 3) {
                throw new \InvalidArgumentException('Invalid beverage style type.');
            }
        }
    }
}
