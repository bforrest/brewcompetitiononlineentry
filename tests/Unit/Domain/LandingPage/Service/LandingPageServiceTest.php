<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\LandingPage\Service\LandingPageCopyAdapter;
use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;

final class LandingPageServiceTest extends TestCase
{
    public function test_open_registration_builds_open_cta_and_no_closed_alert(): void
    {
        $view = $this->service()->viewFor(
            Identity::fromSession([]),
            new LandingPageContext('en-US', null, [0, 1]),
            1500,
        );

        self::assertSame(WindowStatus::Open, $view->registrationStatus);
        self::assertSame('/register', $view->links->register);
        self::assertFalse($view->loggedIn);
        self::assertNotEmpty($view->hero->imageUrl);
        self::assertTrue($this->hasAlert($view->alerts, 'Entry registration is open!'));
        self::assertFalse($this->hasAlert($view->alerts, 'Account registration is closed.'));
    }

    public function test_upcoming_registration_builds_upcoming_alert(): void
    {
        $view = $this->service(
            windows: $this->windows(registrationOpensAt: 2000),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame(WindowStatus::Upcoming, $view->registrationStatus);
        self::assertTrue($this->hasAlert($view->alerts, 'Account registration has not opened yet.'));
    }

    public function test_closed_registration_with_open_judge_window_builds_judge_cta(): void
    {
        $view = $this->service(
            windows: $this->windows(registrationClosesAt: 1400),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame(WindowStatus::Closed, $view->registrationStatus);
        self::assertSame(WindowStatus::Open, $view->judgeStatus);
        self::assertTrue($this->hasAlert($view->alerts, 'Judge or steward registration is open.'));
        self::assertSame('/register', $this->alertWithMessage($view->alerts, 'Judge or steward registration is open.')->linkUrl);
    }

    public function test_started_judging_forces_registration_and_entry_closed(): void
    {
        $view = $this->service(
            judging: new JudgingProgress(true, false, false, 0),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame(WindowStatus::Closed, $view->registrationStatus);
        self::assertSame(WindowStatus::Closed, $view->entryStatus);
    }

    public function test_entry_limit_builds_capacity_alert(): void
    {
        $view = $this->service(
            limits: new CompetitionLimits(100, 70, 100, 80, 90),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        $alert = $this->alertWithMessage(
            $view->alerts,
            'The limit of 100 entries has been reached. No further entries will be accepted.',
        );
        self::assertSame(AlertLevel::Warning, $alert->level);
    }

    public function test_near_limit_threshold_builds_capacity_warning_only_at_threshold(): void
    {
        $below = $this->service(
            limits: new CompetitionLimits(89, 20, 100, null, 90),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);
        $at = $this->service(
            limits: new CompetitionLimits(90, 20, 100, null, 90),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertFalse($this->hasAlert($below->alerts, '90/100'));
        self::assertTrue($this->hasAlert($at->alerts, '90/100'));
    }

    public function test_missing_optional_windows_are_effectively_open(): void
    {
        $view = $this->service(
            windows: $this->windows(
                dropoffOpensAt: null,
                dropoffClosesAt: null,
                shippingOpensAt: null,
                shippingClosesAt: null,
            ),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame(WindowStatus::Open, $view->dropoffStatus);
        self::assertSame(WindowStatus::Open, $view->shippingStatus);
    }

    public function test_mixed_optional_window_configuration_is_rejected(): void
    {
        $service = $this->service(
            windows: $this->windows(dropoffOpensAt: null, dropoffClosesAt: 2000),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Optional competition window is only partially configured.');

        $service->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);
    }

    public function test_hidden_sponsors_and_empty_contacts_produce_empty_regions(): void
    {
        $view = $this->service(
            contacts: [],
            sponsors: [],
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame([], $view->contacts);
        self::assertSame([], $view->sponsors);
    }

    public function test_winner_delay_hides_winner_rows_until_release(): void
    {
        $view = $this->service(
            judging: new JudgingProgress(true, true, true, 2000),
            winners: $this->winners(),
        )->viewFor(Identity::fromSession([]), new LandingPageContext('en-US', null, [1]), 1500);

        self::assertSame([], $view->winners->rows);
        self::assertTrue($this->hasAlert($view->alerts, 'Winning entries have not been posted yet. Please check back later.'));
    }

    public function test_authenticated_viewer_gets_account_links_and_safe_greeting(): void
    {
        $view = $this->service()->viewFor(
            Identity::fromSession(['loginUsername' => 'entrant@example.test', 'userLevel' => '2']),
            new LandingPageContext('en-US', '  <Ada & Co>  ', [1]),
            1500,
        );

        self::assertTrue($view->loggedIn);
        self::assertSame('<Ada & Co>', $view->viewerName);
        self::assertSame('/index.php?section=list', $view->links->account);
        self::assertSame('/includes/process.inc.php?section=logout&action=logout', $view->links->logout);
    }

    public function test_blank_viewer_name_falls_back_to_identity_username(): void
    {
        $view = $this->service()->viewFor(
            Identity::fromSession(['loginUsername' => 'entrant@example.test', 'userLevel' => '2']),
            new LandingPageContext('en-US', " \t ", [1]),
            1500,
        );

        self::assertSame('entrant@example.test', $view->viewerName);
    }

    public function test_malformed_hero_preferences_fall_back_to_bundled_image(): void
    {
        $view = $this->service()->viewFor(
            Identity::fromSession([]),
            new LandingPageContext('en-US', null, []),
            1500,
        );

        self::assertSame('/images/misc-cropped-bottles_3000x500.jpg', $view->hero->imageUrl);
        self::assertSame('Fixture Competition', $view->hero->heading);
        self::assertSame('Fixture Host', $view->hero->subheading);
    }

    public function test_configured_beverage_type_selects_matching_bundled_image(): void
    {
        $view = $this->service()->viewFor(
            Identity::fromSession([]),
            new LandingPageContext('en-US', null, [2]),
            1500,
        );

        self::assertSame('/images/cider-bottles_3000x500.jpg', $view->hero->imageUrl);
    }

    private function service(
        ?CompetitionWindows $windows = null,
        ?CompetitionLimits $limits = null,
        ?JudgingProgress $judging = null,
        ?array $contacts = null,
        ?array $sponsors = null,
        ?WinnerSummary $winners = null,
    ): LandingPageService {
        $repository = $this->createMock(LandingPageReadRepository::class);
        $repository->method('contestOverview')->willReturn(
            new ContestOverview(
                'Fixture Competition',
                'Fixture Host',
                'https://host.example.test',
                'Austin, Texas',
                '/user_images/logo.png',
            ),
        );
        $repository->method('competitionWindows')->willReturn($windows ?? $this->windows());
        $repository->method('competitionLimits')->willReturn(
            $limits ?? new CompetitionLimits(5, 3, 100, 80, 90),
        );
        $repository->method('judgingProgress')->willReturn(
            $judging ?? new JudgingProgress(false, false, true, 0),
        );
        $repository->method('locations')->willReturn(
            new CompetitionLocations(null, null, null, null, null, null),
        );
        $repository->method('contacts')->willReturn(
            $contacts ?? [new Contact('Ada', 'Brewer', 'Organizer', 'ada@example.test')],
        );
        $repository->method('sponsors')->willReturn(
            $sponsors ?? [new Sponsor('Fixture Sponsor', null, null, null, null, 1)],
        );
        $repository->method('visibleArchives')->willReturn(
            [new Archive('2025', 0, 'BJCP2021')],
        );
        $repository->method('winnerSummary')->willReturn($winners ?? $this->winners());

        return new LandingPageService($repository, new LandingPageCopyAdapter());
    }

    private function windows(
        int $registrationOpensAt = 1000,
        int $registrationClosesAt = 2000,
        int $entryOpensAt = 1000,
        int $entryClosesAt = 2000,
        int $judgeOpensAt = 1000,
        int $judgeClosesAt = 2000,
        ?int $dropoffOpensAt = 1000,
        ?int $dropoffClosesAt = 2000,
        ?int $shippingOpensAt = 1000,
        ?int $shippingClosesAt = 2000,
    ): CompetitionWindows {
        return new CompetitionWindows(
            $registrationOpensAt,
            $registrationClosesAt,
            $entryOpensAt,
            $entryClosesAt,
            $judgeOpensAt,
            $judgeClosesAt,
            $dropoffOpensAt,
            $dropoffClosesAt,
            $shippingOpensAt,
            $shippingClosesAt,
        );
    }

    private function winners(): WinnerSummary
    {
        return new WinnerSummary(
            WinnerMethod::Overall,
            'BJCP2021',
            [
                new WinnerRow(
                    'Table 1',
                    10,
                    1,
                    'Ada Brewer',
                    null,
                    'Winning Entry',
                    '1A: American Light Lager',
                    null,
                    'Fixture Club',
                    42.5,
                ),
            ],
        );
    }

    /** @param list<\Bcoem\Domain\LandingPage\Presentation\Alert> $alerts */
    private function hasAlert(array $alerts, string $messageFragment): bool
    {
        foreach ($alerts as $alert) {
            if (str_contains($alert->message, $messageFragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<\Bcoem\Domain\LandingPage\Presentation\Alert> $alerts
     */
    private function alertWithMessage(
        array $alerts,
        string $message,
    ): \Bcoem\Domain\LandingPage\Presentation\Alert {
        foreach ($alerts as $alert) {
            if ($alert->message === $message) {
                return $alert;
            }
        }

        self::fail('Expected alert was not found: ' . $message);
    }
}
