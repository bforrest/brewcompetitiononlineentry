<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final readonly class ContestOverview
{
    public function __construct(
        public string $name,
        public string $hostName,
        public ?string $hostWebsite,
        public ?string $hostLocation,
        public ?string $logoPath,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Contest name must not be blank.');
        }
        if (trim($hostName) === '') {
            throw new \InvalidArgumentException('Host name must not be blank.');
        }
        SafeUrl::assert($hostWebsite);
        SafeUrl::assert($logoPath);
    }
}
