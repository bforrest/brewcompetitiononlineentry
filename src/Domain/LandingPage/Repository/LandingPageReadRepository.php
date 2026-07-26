<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Repository;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;

interface LandingPageReadRepository
{
    public function contestOverview(): ?ContestOverview;

    public function competitionWindows(): ?CompetitionWindows;

    public function competitionLimits(): CompetitionLimits;

    public function judgingProgress(int $now): JudgingProgress;

    public function locations(): CompetitionLocations;

    /** @return list<Contact> */
    public function contacts(): array;

    /** @return list<Sponsor> */
    public function sponsors(): array;

    /** @return list<Archive> */
    public function visibleArchives(): array;

    public function winnerSummary(): WinnerSummary;
}
