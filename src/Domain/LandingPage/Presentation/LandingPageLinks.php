<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageLinks
{
    public function __construct(
        public string $register,
        public string $login,
        public string $logout,
        public string $contact,
        public string $sponsors,
        public ?string $hostWebsite,
        public string $resultsPdf,
        public string $resultsHtml,
    ) {
        foreach ([$register, $login, $logout, $contact, $sponsors, $hostWebsite, $resultsPdf, $resultsHtml] as $url) {
            self::assertSafeUrl($url);
        }
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
