<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

final readonly class LandingPageCopy
{
    public function __construct(
        public string $register,
        public string $login,
        public string $logout,
        public string $rules,
        public string $volunteers,
        public string $entryInfo,
        public string $contact,
        public string $sponsors,
        public string $officials,
        public string $results,
        public string $pastWinners,
        public string $upcomingMessage,
        public string $openMessage,
        public string $closedMessage,
        public string $judgeOpenMessage,
        public string $entryLimitMessage,
        public string $nearLimitMessage,
        public string $paidEntryLimitMessage,
        public string $winnerDelayMessage,
    ) {
    }
}
