<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Validation;

final class SafeUrl
{
    public static function assert(?string $url): void
    {
        if ($url === null) {
            return;
        }

        if ($url === ''
            || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new \InvalidArgumentException('Only relative, HTTP, and HTTPS URLs are allowed.');
        }

        if (preg_match('/^#[A-Za-z][A-Za-z0-9_.:-]*$/D', $url) === 1) {
            return;
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//') || parse_url($url) === false) {
                throw new \InvalidArgumentException('Only relative, HTTP, and HTTPS URLs are allowed.');
            }
            return;
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if (!in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Only relative, HTTP, and HTTPS URLs are allowed.');
        }
    }
}
