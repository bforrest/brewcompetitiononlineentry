<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Form;

/**
 * Adapts the registration-specific static lists from legacy constants without
 * loading that file and its unrelated session, cache, and network side effects.
 */
final class LegacyEntrantChoiceSource
{
    /** @var array{countries: array<string, string>, states: array<string, string>}|null */
    private static ?array $choices = null;

    /** @return array<string, string> */
    public function countryChoices(): array
    {
        return $this->choices()['countries'];
    }

    /** @return array<string, string> */
    public function stateChoices(): array
    {
        return $this->choices()['states'];
    }

    /** @return list<string> */
    public function securityQuestions(): array
    {
        $language = file_get_contents(dirname(__DIR__, 4) . '/lang/en/en-US.lang.php');
        if ($language === false || preg_match_all('/\$label_secret_\d+\s*=\s*"([^"]+)";/', $language, $matches) < 1) {
            throw new \RuntimeException('Legacy security questions are unavailable.');
        }

        return array_values(array_unique(array_map(
            static fn (string $question): string => html_entity_decode($question, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches[1],
        )));
    }

    /** @return array{countries: array<string, string>, states: array<string, string>} */
    private function choices(): array
    {
        if (self::$choices !== null) {
            return self::$choices;
        }

        $constants = file_get_contents(dirname(__DIR__, 4) . '/includes/constants.inc.php');
        if ($constants === false
            || preg_match('/\\$countries = array\\((.*?)\\);/s', $constants, $countryMatch) !== 1
            || preg_match('/\\$us_state_abbrevs_names = array\\((.*?)\\);/s', $constants, $stateMatch) !== 1) {
            throw new \RuntimeException('Legacy entrant choice constants are unavailable.');
        }

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $countryMatch[1], $countryValues);
        $countries = [];
        foreach ($countryValues[1] as $country) {
            $country = stripcslashes($country);
            $countries[$country] = $country;
        }
        asort($countries);

        preg_match_all("/'([^']+)'\\s*=>\\s*'([^']+)'/", $stateMatch[1], $stateValues, PREG_SET_ORDER);
        $states = [];
        foreach ($stateValues as $state) {
            $states[$state[1]] = $state[2] . ' [' . $state[1] . ']';
        }

        return self::$choices = ['countries' => $countries, 'states' => $states];
    }
}
