<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

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
        self::assertSafeUrl($hostWebsite);
        self::assertSafeUrl($logoPath);
    }

    private static function assertSafeUrl(?string $url): void
    {
        if ($url === null || str_starts_with($url, '/')) {
            return;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only relative, HTTP, and HTTPS URLs are allowed.');
        }
    }
}
