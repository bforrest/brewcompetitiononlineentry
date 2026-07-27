<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;

final class LandingPageCopyAdapter
{
    public function forLocale(string $locale): LandingPageCopy
    {
        $catalogFile = match ($locale) {
            'en-GB' => 'en-GB.php',
            'es-419' => 'es-419.php',
            default => 'en-US.php',
        };
        /** @var array<string, string> $catalog */
        $catalog = require dirname(__DIR__) . '/Resources/' . $catalogFile;

        return new LandingPageCopy(
            register: $catalog['register'],
            login: $catalog['login'],
            logout: $catalog['logout'],
            rules: $catalog['rules'],
            volunteers: $catalog['volunteers'],
            entryInfo: $catalog['entry_info'],
            contact: $catalog['contact'],
            sponsors: $catalog['sponsors'],
            officials: $catalog['officials'],
            results: $catalog['results'],
            pastWinners: $catalog['past_winners'],
            upcomingMessage: $catalog['upcoming_message'],
            openMessage: $catalog['open_message'],
            closedMessage: $catalog['closed_message'],
            judgeOpenMessage: $catalog['judge_open_message'],
            entryLimitMessage: $catalog['entry_limit_message'],
            nearLimitMessage: $catalog['near_limit_message'],
            paidEntryLimitMessage: $catalog['paid_entry_limit_message'],
            winnerDelayMessage: $catalog['winner_delay_message'],
        );
    }
}
