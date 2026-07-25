<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

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
        self::assertSafeUrl($websiteUrl);
        self::assertSafeUrl($imagePath);
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
