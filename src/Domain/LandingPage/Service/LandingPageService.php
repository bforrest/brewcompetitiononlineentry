<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\Alert;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\HeroPresentation;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;
use Bcoem\Domain\LandingPage\Presentation\LandingPageLinks;
use Bcoem\Domain\LandingPage\Presentation\LandingPageViewModel;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\Shared\ValueObject\DateWindow;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use Bcoem\Security\Identity;

final class LandingPageService
{
    public function __construct(
        private LandingPageReadRepository $repository,
        private LandingPageCopyAdapter $copy,
    ) {
    }

    public function viewFor(Identity $identity, LandingPageContext $context, int $now): LandingPageViewModel
    {
        $contest = $this->repository->contestOverview()
            ?? throw new \RuntimeException('Contest overview is not configured.');
        $windows = $this->repository->competitionWindows()
            ?? throw new \RuntimeException('Competition windows are not configured.');
        $judging = $this->repository->judgingProgress($now);

        $registration = $this->status(
            $windows->registrationOpensAt,
            $windows->registrationClosesAt,
            $now,
        );
        $entry = $this->status($windows->entryOpensAt, $windows->entryClosesAt, $now);
        $judge = $this->status($windows->judgeOpensAt, $windows->judgeClosesAt, $now);
        $dropoff = $this->optionalStatus(
            $windows->dropoffOpensAt,
            $windows->dropoffClosesAt,
            $now,
        );
        $shipping = $this->optionalStatus(
            $windows->shippingOpensAt,
            $windows->shippingClosesAt,
            $now,
        );

        $capacity = $this->repository->competitionLimits();
        if ($judging->started) {
            $registration = WindowStatus::Closed;
            $entry = WindowStatus::Closed;
        }
        if ($this->capacityReached($capacity)) {
            $entry = WindowStatus::Closed;
        }

        $copy = $this->copyForView(
            $this->copy->forLocale($context->locale),
            $windows->registrationOpensAt,
        );

        return new LandingPageViewModel(
            contest: $contest,
            loggedIn: $identity->loggedIn,
            viewerName: $this->viewerName($identity, $context),
            registrationStatus: $registration,
            entryStatus: $entry,
            judgeStatus: $judge,
            dropoffStatus: $dropoff,
            shippingStatus: $shipping,
            capacity: $capacity,
            judging: $judging,
            locations: $this->repository->locations(),
            alerts: $this->buildAlerts(
                $registration,
                $entry,
                $judge,
                $capacity,
                $judging,
                $copy,
                $now,
                $identity->loggedIn,
            ),
            contacts: $this->repository->contacts(),
            sponsors: $this->repository->sponsors(),
            archives: $this->repository->visibleArchives(),
            winners: $this->visibleWinners($judging, $now),
            hero: $this->heroFor($contest, $context->beverageStyleTypes),
            links: $this->linksFor($contest),
            copy: $copy,
        );
    }

    private function status(int $opensAt, int $closesAt, int $now): WindowStatus
    {
        return (new DateWindow($opensAt, $closesAt))->statusAt($now);
    }

    private function optionalStatus(?int $opensAt, ?int $closesAt, int $now): WindowStatus
    {
        if ($opensAt === null && $closesAt === null) {
            return WindowStatus::Open;
        }
        if ($opensAt === null || $closesAt === null) {
            throw new \RuntimeException('Optional competition window is only partially configured.');
        }

        return $this->status($opensAt, $closesAt, $now);
    }

    private function viewerName(Identity $identity, LandingPageContext $context): ?string
    {
        if (!$identity->loggedIn) {
            return null;
        }

        $firstName = trim($context->viewerName ?? '');
        if ($firstName !== '') {
            return $firstName;
        }

        return $identity->username;
    }

    /**
     * @return list<Alert>
     */
    private function buildAlerts(
        WindowStatus $registration,
        WindowStatus $entry,
        WindowStatus $judge,
        CompetitionLimits $capacity,
        JudgingProgress $judging,
        LandingPageCopy $copy,
        int $now,
        bool $loggedIn,
    ): array {
        $alerts = [];

        if (!$loggedIn) {
            if ($registration === WindowStatus::Upcoming) {
                $alerts[] = new Alert(AlertLevel::Info, $copy->upcomingMessage);
            } elseif ($registration === WindowStatus::Open) {
                $alerts[] = new Alert(AlertLevel::Success, $copy->openMessage, $copy->register, '/register');
            } else {
                $alerts[] = new Alert(AlertLevel::Warning, $copy->closedMessage, $copy->login, '/index.php?section=login');
            }

            if ($registration !== WindowStatus::Open && $judge === WindowStatus::Open) {
                $alerts[] = new Alert(
                    AlertLevel::Info,
                    $copy->judgeOpenMessage,
                    $copy->register,
                    '/register',
                );
            }
        }

        if ($registration === WindowStatus::Open) {
            $alerts = [
                ...$alerts,
                ...$this->reachedCapacityAlerts($capacity, $copy),
            ];
            if (!$this->capacityReached($capacity) && $entry === WindowStatus::Open) {
                $alerts = [
                    ...$alerts,
                    ...$this->nearCapacityAlerts($capacity, $copy),
                ];
            }
        }

        if ($judging->displayWinners && $now < $judging->winnerReleaseAt) {
            $alerts[] = new Alert(AlertLevel::Info, $copy->winnerDelayMessage);
        }

        return $alerts;
    }

    /** @return list<Alert> */
    private function reachedCapacityAlerts(CompetitionLimits $capacity, LandingPageCopy $copy): array
    {
        $alerts = [];

        if ($capacity->entryLimit !== null && $capacity->entryCount >= $capacity->entryLimit) {
            $alerts[] = new Alert(
                AlertLevel::Warning,
                sprintf($copy->entryLimitMessage, $capacity->entryLimit),
            );
        }

        if (
            $capacity->paidEntryLimit !== null
            && $capacity->paidEntryCount >= $capacity->paidEntryLimit
        ) {
            $alerts[] = new Alert(
                AlertLevel::Warning,
                sprintf($copy->paidEntryLimitMessage, $capacity->paidEntryLimit),
            );
        }

        return $alerts;
    }

    /** @return list<Alert> */
    private function nearCapacityAlerts(CompetitionLimits $capacity, LandingPageCopy $copy): array
    {
        if (
            $capacity->entryLimit === null
            || $capacity->nearLimitThreshold <= 0
            || $capacity->entryCount < $capacity->nearLimitThreshold
        ) {
            return [];
        }

        return [
            new Alert(
                AlertLevel::Warning,
                sprintf(
                    $copy->nearLimitMessage,
                    $capacity->entryCount,
                    $capacity->entryLimit,
                ),
            ),
        ];
    }

    private function capacityReached(CompetitionLimits $capacity): bool
    {
        return (
            $capacity->entryLimit !== null
            && $capacity->entryCount >= $capacity->entryLimit
        ) || (
            $capacity->paidEntryLimit !== null
            && $capacity->paidEntryCount >= $capacity->paidEntryLimit
        );
    }

    private function visibleWinners(JudgingProgress $judging, int $now): WinnerSummary
    {
        if (
            !$judging->ended
            || !$judging->displayWinners
            || $now < $judging->winnerReleaseAt
        ) {
            return new WinnerSummary(WinnerMethod::Overall, '', []);
        }

        return $this->repository->winnerSummary();
    }

    /** @param list<int> $beverageStyleTypes */
    private function heroFor(ContestOverview $contest, array $beverageStyleTypes): HeroPresentation
    {
        $imagesByType = [
            0 => 'misc-cropped-bottles_3000x500.jpg',
            1 => 'beer-barley-malt_3000x500.jpg',
            2 => 'cider-bottles_3000x500.jpg',
            3 => 'mead-bottles_3000x500.jpg',
        ];
        $image = $imagesByType[0];
        foreach ($beverageStyleTypes as $type) {
            if (isset($imagesByType[$type])) {
                $image = $imagesByType[$type];
                break;
            }
        }

        return new HeroPresentation(
            '/images/' . $image,
            $contest->name,
            $contest->hostName,
        );
    }

    private function linksFor(ContestOverview $contest): LandingPageLinks
    {
        $resultsBase = '/includes/output.inc.php?section=export-results'
            . '&go=judging_scores&action=default&filter=default&view=';

        return new LandingPageLinks(
            register: '/register',
            login: '/index.php?section=login',
            logout: '/includes/process.inc.php?section=logout&action=logout',
            account: '/index.php?section=list',
            contact: '/#contact',
            sponsors: '/#sponsors',
            hostWebsite: $contest->hostWebsite,
            resultsPdf: $resultsBase . 'pdf',
            resultsHtml: $resultsBase . 'html',
        );
    }

    private function copyForView(LandingPageCopy $copy, int $registrationOpensAt): LandingPageCopy
    {
        return new LandingPageCopy(
            register: $copy->register,
            login: $copy->login,
            logout: $copy->logout,
            rules: $copy->rules,
            volunteers: $copy->volunteers,
            entryInfo: $copy->entryInfo,
            contact: $copy->contact,
            sponsors: $copy->sponsors,
            officials: $copy->officials,
            results: $copy->results,
            upcomingMessage: sprintf(
                $copy->upcomingMessage,
                gmdate('F j, Y g:i A \U\T\C', $registrationOpensAt),
            ),
            openMessage: $copy->openMessage,
            closedMessage: $copy->closedMessage,
            judgeOpenMessage: $copy->judgeOpenMessage,
            entryLimitMessage: $copy->entryLimitMessage,
            nearLimitMessage: $copy->nearLimitMessage,
            paidEntryLimitMessage: $copy->paidEntryLimitMessage,
            winnerDelayMessage: $copy->winnerDelayMessage,
        );
    }
}
