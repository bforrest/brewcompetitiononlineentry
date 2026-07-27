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
            home: $catalog['home'],
            toggleNavigation: $catalog['toggle_navigation'],
            account: $catalog['account'],
            hello: $catalog['hello'],
            hostedBy: $catalog['hosted_by'],
            atAGlance: $catalog['at_a_glance'],
            entries: $catalog['entries'],
            paidEntries: $catalog['paid_entries'],
            entryStatus: $catalog['entry_status'],
            dropoffStatus: $catalog['dropoff_status'],
            shippingStatus: $catalog['shipping_status'],
            statusUpcoming: $catalog['status_upcoming'],
            statusOpen: $catalog['status_open'],
            statusClosed: $catalog['status_closed'],
            shipping: $catalog['shipping'],
            awards: $catalog['awards'],
            awardsTime: $catalog['awards_time'],
            registrationDates: $catalog['registration_dates'],
            entryDates: $catalog['entry_dates'],
            judgeDates: $catalog['judge_dates'],
            dropoffDates: $catalog['dropoff_dates'],
            shippingDates: $catalog['shipping_dates'],
            opens: $catalog['opens'],
            closes: $catalog['closes'],
            editAccount: $catalog['edit_account'],
            addEntry: $catalog['add_entry'],
            registerJudge: $catalog['register_judge'],
            registerSteward: $catalog['register_steward'],
            entryAcceptance: $catalog['entry_acceptance'],
            dropoffLocations: $catalog['dropoff_locations'],
            bestOfShow: $catalog['best_of_show'],
            place: $catalog['place'],
            entry: $catalog['entry'],
            brewer: $catalog['brewer'],
            style: $catalog['style'],
            score: $catalog['score'],
            emailAddress: $catalog['email_address'],
            password: $catalog['password'],
            validEmailRequired: $catalog['valid_email_required'],
            passwordRequired: $catalog['password_required'],
            close: $catalog['close'],
            visitSponsor: $catalog['visit_sponsor'],
        );
    }
}
