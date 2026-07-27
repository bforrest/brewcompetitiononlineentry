<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\BestOfShowSummary;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\Alert;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\HeroPresentation;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;
use Bcoem\Domain\LandingPage\Presentation\LandingAction;
use Bcoem\Domain\LandingPage\Presentation\LandingPageActions;
use Bcoem\Domain\LandingPage\Presentation\LandingPageDateRange;
use Bcoem\Domain\LandingPage\Presentation\LandingPageDates;
use Bcoem\Domain\LandingPage\Presentation\LandingPageLinks;
use Bcoem\Domain\LandingPage\Presentation\LandingPageSections;
use Bcoem\Domain\LandingPage\Presentation\LandingPageViewModel;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\Shared\ValueObject\DateWindow;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use Bcoem\Security\Identity;

final class LandingPageService
{
    private const REGISTER_URL = '/index.php?section=register&go=entrant';
    private const LOGIN_TARGET = '#login-modal';
    private const EDIT_ACCOUNT_URL = '/index.php?section=brewer&go=account&action=edit';
    private const ADD_ENTRY_URL = '/index.php?section=brew&go=entry&action=add';
    private const JUDGE_URL = '/index.php?section=register&go=judge';
    private const STEWARD_URL = '/index.php?section=register&go=steward';

    private HeroImageSelector $heroSelector;
    private ContactLinkEncoder $contactLinkEncoder;

    public function __construct(
        private LandingPageReadRepository $repository,
        private LandingPageCopyAdapter $copy,
        ?HeroImageSelector $heroSelector = null,
        ?ContactLinkEncoder $contactLinkEncoder = null,
    ) {
        $this->heroSelector = $heroSelector ?? new RandomHeroImageSelector();
        $this->contactLinkEncoder = $contactLinkEncoder ?? new LegacyContactLinkEncoder('', '');
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
            $context,
        );
        $locations = $this->repository->locations();
        $rules = $this->repository->competitionRules();
        $contactMode = $this->repository->contactMode();
        $winnerResultsVisible = $this->winnerResultsVisible($judging, $registration, $entry, $now);
        $contacts = $this->contactsFor($contactMode);
        $sponsors = $this->repository->sponsors();

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
            winnerResultsVisible: $winnerResultsVisible,
            locations: $locations,
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
            contacts: $contacts,
            sponsors: $sponsors,
            archives: $this->repository->visibleArchives(),
            winners: $this->visibleWinners($judging, $registration, $entry, $now),
            hero: $this->heroFor($contest, $context->beverageStyleTypes),
            links: $this->linksFor($contest),
            copy: $copy,
            rules: $rules,
            dates: $this->datesFor($windows, $locations->awardsAt, $context),
            actions: $this->actionsFor(
                $identity->loggedIn,
                $registration,
                $entry,
                $judge,
                $capacity,
                $copy,
            ),
            sections: $this->sectionsFor(
                $judging,
                $registration,
                $entry,
                $contactMode,
                $sponsors !== [],
            ),
            contactMode: $contactMode,
            bestOfShow: $winnerResultsVisible
                ? $this->repository->bestOfShow()
                : new BestOfShowSummary([]),
            locale: $context->locale,
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
                $alerts[] = new Alert(
                    AlertLevel::Success,
                    $copy->openMessage,
                    $copy->register,
                    self::REGISTER_URL,
                );
            } else {
                $alerts[] = new Alert(
                    AlertLevel::Warning,
                    $copy->closedMessage,
                    $copy->login,
                    self::LOGIN_TARGET,
                );
            }

            if ($registration !== WindowStatus::Open && $judge === WindowStatus::Open) {
                $alerts[] = new Alert(
                    AlertLevel::Info,
                    $copy->judgeOpenMessage,
                    $copy->register,
                    self::REGISTER_URL,
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

        if (
            $judging->resultsStage
            && $registration === WindowStatus::Closed
            && $entry === WindowStatus::Closed
            && $judging->displayWinners
            && $now <= $judging->winnerReleaseAt
        ) {
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

    private function visibleWinners(
        JudgingProgress $judging,
        WindowStatus $registration,
        WindowStatus $entry,
        int $now,
    ): WinnerSummary
    {
        if (!$this->winnerResultsVisible($judging, $registration, $entry, $now)) {
            return new WinnerSummary(WinnerMethod::Overall, '', []);
        }

        return $this->repository->winnerSummary();
    }

    private function winnerResultsVisible(
        JudgingProgress $judging,
        WindowStatus $registration,
        WindowStatus $entry,
        int $now,
    ): bool
    {
        return $judging->resultsStage
            && $registration === WindowStatus::Closed
            && $entry === WindowStatus::Closed
            && $judging->displayWinners
            && $now > $judging->winnerReleaseAt;
    }

    /** @param list<int> $beverageStyleTypes */
    private function heroFor(ContestOverview $contest, array $beverageStyleTypes): HeroPresentation
    {
        $imagesByType = [
            0 => [
                'misc-cropped-bottles_3000x500.jpg',
                'misc-brussels-bottles_3000x500.jpg',
                'misc-plzen-fermenters_3000x500.jpg',
                'misc-bottles_3000x500.jpg',
            ],
            1 => [
                'beer-barley-malt_3000x500.jpg',
                'beer-brussels-barrels_3000x500.jpg',
                'beer-hop-cones_3000x500.jpg',
                'beer-kegs_3000x500.jpg',
                'beer-munich-mugs_3000x500.jpg',
                'beer-on-bar_3000x500.jpg',
            ],
            2 => ['cider-bottles_3000x500.jpg'],
            3 => ['mead-bottles_3000x500.jpg'],
        ];
        $candidates = $imagesByType[0];
        foreach (array_values(array_unique($beverageStyleTypes)) as $type) {
            if ($type !== 0 && isset($imagesByType[$type])) {
                array_push($candidates, ...$imagesByType[$type]);
            }
        }
        $image = $this->heroSelector->select($candidates);
        if (!in_array($image, $candidates, true)) {
            $image = $imagesByType[0][0];
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
            register: self::REGISTER_URL,
            login: self::LOGIN_TARGET,
            logout: '/includes/process.inc.php?section=logout&action=logout',
            account: '/index.php?section=list',
            contact: '/#contact',
            sponsors: '/#sponsors',
            hostWebsite: $contest->hostWebsite,
            resultsPdf: $resultsBase . 'pdf',
            resultsHtml: $resultsBase . 'html',
        );
    }

    private function copyForView(
        LandingPageCopy $copy,
        int $registrationOpensAt,
        LandingPageContext $context,
    ): LandingPageCopy
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
            pastWinners: $copy->pastWinners,
            upcomingMessage: sprintf(
                $copy->upcomingMessage,
                $this->formatDateTime($registrationOpensAt, $context),
            ),
            openMessage: $copy->openMessage,
            closedMessage: $copy->closedMessage,
            judgeOpenMessage: $copy->judgeOpenMessage,
            entryLimitMessage: $copy->entryLimitMessage,
            nearLimitMessage: $copy->nearLimitMessage,
            paidEntryLimitMessage: $copy->paidEntryLimitMessage,
            winnerDelayMessage: $copy->winnerDelayMessage,
            home: $copy->home,
            toggleNavigation: $copy->toggleNavigation,
            account: $copy->account,
            hello: $copy->hello,
            hostedBy: $copy->hostedBy,
            atAGlance: $copy->atAGlance,
            entries: $copy->entries,
            paidEntries: $copy->paidEntries,
            entryStatus: $copy->entryStatus,
            dropoffStatus: $copy->dropoffStatus,
            shippingStatus: $copy->shippingStatus,
            statusUpcoming: $copy->statusUpcoming,
            statusOpen: $copy->statusOpen,
            statusClosed: $copy->statusClosed,
            shipping: $copy->shipping,
            awards: $copy->awards,
            awardsTime: $copy->awardsTime,
            registrationDates: $copy->registrationDates,
            entryDates: $copy->entryDates,
            judgeDates: $copy->judgeDates,
            dropoffDates: $copy->dropoffDates,
            shippingDates: $copy->shippingDates,
            opens: $copy->opens,
            closes: $copy->closes,
            editAccount: $copy->editAccount,
            addEntry: $copy->addEntry,
            registerJudge: $copy->registerJudge,
            registerSteward: $copy->registerSteward,
            entryAcceptance: $copy->entryAcceptance,
            dropoffLocations: $copy->dropoffLocations,
            bestOfShow: $copy->bestOfShow,
            place: $copy->place,
            entry: $copy->entry,
            brewer: $copy->brewer,
            style: $copy->style,
            score: $copy->score,
            emailAddress: $copy->emailAddress,
            password: $copy->password,
            validEmailRequired: $copy->validEmailRequired,
            passwordRequired: $copy->passwordRequired,
            close: $copy->close,
            visitSponsor: $copy->visitSponsor,
        );
    }

    /** @return list<Contact> */
    private function contactsFor(ContactMode $mode): array
    {
        if ($mode === ContactMode::Hidden) {
            return [];
        }

        return array_map(
            fn (Contact $contact): Contact => new Contact(
                $contact->id,
                $contact->firstName,
                $contact->lastName,
                $contact->position,
                $mode === ContactMode::Directory
                    ? $this->contactLinkEncoder->destinationFor($contact->id)
                    : '/index.php?section=contact',
            ),
            $this->repository->contacts(),
        );
    }

    private function actionsFor(
        bool $loggedIn,
        WindowStatus $registration,
        WindowStatus $entry,
        WindowStatus $judge,
        CompetitionLimits $capacity,
        LandingPageCopy $copy,
    ): LandingPageActions {
        $editAccount = new LandingAction($copy->editAccount, self::EDIT_ACCOUNT_URL);

        return new LandingPageActions(
            account: $registration === WindowStatus::Open
                ? ($loggedIn ? $editAccount : new LandingAction($copy->register, self::REGISTER_URL))
                : null,
            entry: $entry === WindowStatus::Open && !$this->capacityReached($capacity)
                ? ($loggedIn
                    ? new LandingAction($copy->addEntry, self::ADD_ENTRY_URL)
                    : new LandingAction($copy->login, self::LOGIN_TARGET))
                : null,
            judge: $judge === WindowStatus::Open
                ? ($loggedIn ? $editAccount : new LandingAction($copy->registerJudge, self::JUDGE_URL))
                : null,
            steward: $judge === WindowStatus::Open
                ? ($loggedIn ? $editAccount : new LandingAction($copy->registerSteward, self::STEWARD_URL))
                : null,
        );
    }

    private function sectionsFor(
        JudgingProgress $judging,
        WindowStatus $registration,
        WindowStatus $entry,
        ContactMode $contactMode,
        bool $hasSponsors,
    ): LandingPageSections {
        $resultsState = $judging->resultsStage
            && $registration === WindowStatus::Closed
            && $entry === WindowStatus::Closed;

        return new LandingPageSections(
            atAGlance: !$resultsState,
            rules: !$resultsState,
            entryInfo: !$resultsState,
            volunteers: !$judging->started,
            winners: $resultsState,
            contact: $contactMode !== ContactMode::Hidden,
            sponsors: $hasSponsors,
        );
    }

    private function datesFor(
        CompetitionWindows $windows,
        ?int $awardsAt,
        LandingPageContext $context,
    ): LandingPageDates {
        return new LandingPageDates(
            registration: $this->dateRange(
                $windows->registrationOpensAt,
                $windows->registrationClosesAt,
                $context,
            ),
            entries: $this->dateRange($windows->entryOpensAt, $windows->entryClosesAt, $context),
            judges: $this->dateRange($windows->judgeOpensAt, $windows->judgeClosesAt, $context),
            dropoff: $this->dateRange($windows->dropoffOpensAt, $windows->dropoffClosesAt, $context),
            shipping: $this->dateRange($windows->shippingOpensAt, $windows->shippingClosesAt, $context),
            awards: $awardsAt === null ? null : $this->formatDateTime($awardsAt, $context),
        );
    }

    private function dateRange(
        ?int $opensAt,
        ?int $closesAt,
        LandingPageContext $context,
    ): LandingPageDateRange {
        return new LandingPageDateRange(
            $opensAt === null ? null : $this->formatDateTime($opensAt, $context),
            $closesAt === null ? null : $this->formatDateTime($closesAt, $context),
            $opensAt,
            $closesAt,
        );
    }

    private function formatDateTime(int $timestamp, LandingPageContext $context): string
    {
        $dateFormat = match ($context->dateFormat) {
            1 => 'm/d/Y',
            2 => 'd/m/Y',
            default => 'Y/m/d',
        };
        $timeFormat = $context->timeFormat === 1 ? 'H:i' : 'g:i A';
        $date = (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone($context->timezone));

        return $date->format($dateFormat . ' ' . $timeFormat . ', T');
    }
}
