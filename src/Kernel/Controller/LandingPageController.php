<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Kernel\ResponseHelper;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LandingPageController
{
    public function __construct(
        private LandingPageService $service,
        private LayoutRenderer $layout,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $now = time();
        $session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            $identity = Identity::fromSession($this->identitySession($session));
        }

        $stylePreference = is_string($session['prefsSelectedStyles'] ?? null)
            ? json_decode($session['prefsSelectedStyles'], true)
            : null;
        $context = new LandingPageContext(
            locale: $this->supportedLocale($session['prefsLanguage'] ?? null),
            viewerName: $this->viewerName($session['brewerFirstName'] ?? null),
            beverageStyleTypes: $this->styleTypesFromPreference($stylePreference),
        );
        $view = $this->service->viewFor($identity, $context, $now);
        $html = $this->layout->landing(
            $view,
            dirname(__DIR__, 3) . '/templates/LandingPage/home.php',
        );

        return ResponseHelper::html($response, $html);
    }

    private function supportedLocale(mixed $preference): string
    {
        return is_string($preference) && in_array($preference, ['en-US', 'en-GB', 'es-419'], true)
            ? $preference
            : 'en-US';
    }

    private function viewerName(mixed $preference): ?string
    {
        if (!is_scalar($preference)) {
            return null;
        }

        $name = trim((string) $preference);

        return $name === '' ? null : $name;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, string>
     */
    private function identitySession(array $session): array
    {
        $identitySession = [];
        $username = $session['loginUsername'] ?? null;
        if (is_scalar($username)) {
            $identitySession['loginUsername'] = (string) $username;
        }

        $userLevel = $this->userLevel($session['userLevel'] ?? null);
        if ($userLevel !== null) {
            $identitySession['userLevel'] = $userLevel;
        }

        return $identitySession;
    }

    private function userLevel(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= 3 ? (string) $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $level = (int) $value;

        return $level >= 0 && $level <= 3 ? $value : null;
    }

    /**
     * @param mixed $preference
     * @return list<int>
     */
    private function styleTypesFromPreference(mixed $preference): array
    {
        if (!is_array($preference)) {
            return [0];
        }

        $types = [];
        foreach ($preference as $style) {
            if (!is_array($style) || !isset($style['brewStyleType'])) {
                continue;
            }

            $type = $this->styleType($style['brewStyleType']);
            if ($type !== null) {
                $types[] = $type;
            }
        }

        return $types === [] ? [0] : array_values(array_unique($types));
    }

    private function styleType(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 && $value <= 3 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $type = (int) $value;

        return $type >= 1 && $type <= 3 ? $type : null;
    }
}
