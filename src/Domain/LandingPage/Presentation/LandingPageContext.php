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
        public string $timezone = 'UTC',
        public int $dateFormat = 1,
        public int $timeFormat = 0,
    ) {
        if (!in_array($locale, ['en-US', 'en-GB', 'es-419'], true)) {
            throw new \InvalidArgumentException('Unsupported landing-page locale.');
        }
        if (!array_is_list($beverageStyleTypes)) {
            throw new \InvalidArgumentException('Beverage style types must be a list.');
        }
        foreach ($beverageStyleTypes as $type) {
            if (!is_int($type) || $type < 0 || $type > 3) {
                throw new \InvalidArgumentException('Invalid beverage style type.');
            }
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Unsupported landing-page timezone.');
        }
        if (!in_array($dateFormat, [1, 2, 3], true)) {
            throw new \InvalidArgumentException('Unsupported landing-page date format.');
        }
        if (!in_array($timeFormat, [0, 1], true)) {
            throw new \InvalidArgumentException('Unsupported landing-page time format.');
        }
    }
}
