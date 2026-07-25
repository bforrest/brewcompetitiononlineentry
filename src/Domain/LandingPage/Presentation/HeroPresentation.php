<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class HeroPresentation
{
    public function __construct(
        public string $imageUrl,
        public string $heading,
        public string $subheading,
    ) {
        self::assertSafeUrl($imageUrl);
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
