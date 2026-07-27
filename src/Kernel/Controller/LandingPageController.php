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
            timezone: $this->timezone($session['prefsTimeZone'] ?? null),
            dateFormat: $this->dateFormat($session['prefsDateFormat'] ?? null),
            timeFormat: $this->timeFormat($session['prefsTimeFormat'] ?? null),
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

    private function timezone(mixed $preference): string
    {
        if (is_string($preference) && in_array($preference, \DateTimeZone::listIdentifiers(), true)) {
            return $preference;
        }
        if (!is_scalar($preference)) {
            return 'UTC';
        }

        $offsets = [
            '-12.000' => 'Pacific/Kwajalein',
            '-11.000' => 'Pacific/Midway',
            '-10.000' => 'Pacific/Honolulu',
            '-9.500' => 'Pacific/Marquesas',
            '-9.000' => 'America/Anchorage',
            '-8.000' => 'America/Los_Angeles',
            '-7.000' => 'America/Denver',
            '-7.001' => 'America/Phoenix',
            '-6.000' => 'America/Chicago',
            '-6.001' => 'America/Hermosillo',
            '-6.002' => 'America/Regina',
            '-5.000' => 'America/New_York',
            '-4.000' => 'America/Virgin',
            '-4.001' => 'America/Asuncion',
            '-3.500' => 'America/St_Johns',
            '-3.000' => 'America/Argentina/Buenos_Aires',
            '-3.001' => 'America/Sao_Paulo',
            '-2.000' => 'Atlantic/South_Georgia',
            '-1.000' => 'Atlantic/Azores',
            '0.000' => 'Europe/London',
            '1.000' => 'Europe/Paris',
            '2.000' => 'Europe/Helsinki',
            '3.000' => 'Europe/Moscow',
            '3.500' => 'Asia/Tehran',
            '4.000' => 'Asia/Baku',
            '4.500' => 'Asia/Kabul',
            '5.000' => 'Asia/Karachi',
            '5.500' => 'Asia/Calcutta',
            '5.750' => 'Asia/Kathmandu',
            '6.000' => 'Asia/Colombo',
            '7.000' => 'Asia/Bangkok',
            '8.000' => 'Asia/Singapore',
            '8.001' => 'Australia/Perth',
            '9.000' => 'Asia/Tokyo',
            '9.500' => 'Australia/Darwin',
            '10.000' => 'Pacific/Guam',
            '10.001' => 'Australia/Brisbane',
            '10.002' => 'Australia/Melbourne',
            '11.000' => 'Asia/Magadan',
            '12.000' => 'Asia/Kamchatka',
            '13.000' => 'Pacific/Tongatapu',
        ];
        $normalized = number_format((float) $preference, 3, '.', '');

        return $offsets[$normalized] ?? 'UTC';
    }

    private function dateFormat(mixed $preference): int
    {
        $format = is_scalar($preference) ? (int) $preference : 1;

        return in_array($format, [1, 2], true) ? $format : 3;
    }

    private function timeFormat(mixed $preference): int
    {
        return is_scalar($preference) && (int) $preference === 1 ? 1 : 0;
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
