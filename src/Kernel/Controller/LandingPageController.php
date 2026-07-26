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
            $identity = Identity::fromSession($session);
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
     * @param mixed $preference
     * @return list<int>
     */
    private function styleTypesFromPreference(mixed $preference): array
    {
        if (!is_array($preference)) {
            return [0];
        }

        $types = [0];
        foreach ($preference as $style) {
            if (!is_array($style) || !isset($style['brewStyleType'])) {
                continue;
            }

            $type = (int) $style['brewStyleType'];
            if ($type >= 1 && $type <= 3) {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }
}
