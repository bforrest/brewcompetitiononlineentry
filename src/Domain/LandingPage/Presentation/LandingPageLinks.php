<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

use Bcoem\Domain\LandingPage\Validation\SafeUrl;

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
            SafeUrl::assert($url);
        }
    }
}
