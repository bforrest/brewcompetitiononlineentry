<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Model\BestOfShowSummary;
use Bcoem\Domain\LandingPage\Model\BestOfShowWinner;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionRules;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\LandingPage\Service\ContactLinkEncoder;
use Bcoem\Domain\LandingPage\Service\HeroImageSelector;
use Bcoem\Domain\LandingPage\Service\LandingPageCopyAdapter;
use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;

final class LandingPageParityRegressionTest extends TestCase
{
    public function test_exact_winner_release_boundary_remains_hidden_and_bos_appears_afterward(): void
    {
        $service = $this->service(
            judging: new JudgingProgress(true, true, true, 2000, true),
            bos: new BestOfShowSummary([
                new BestOfShowWinner('Beer', 1, 'Ada Brewer', null, 'Winning Ale', '01A: Style', 'Club'),
            ]),
        );
        $context = $this->context();

        $atBoundary = $service->viewFor(Identity::fromSession([]), $context, 2000);
        $afterBoundary = $service->viewFor(Identity::fromSession([]), $context, 2001);

        self::assertFalse($atBoundary->winnerResultsVisible);
        self::assertSame([], $atBoundary->bestOfShow->rows);
        self::assertTrue($afterBoundary->winnerResultsVisible);
        self::assertSame('Winning Ale', $afterBoundary->bestOfShow->rows[0]->entryName);
    }

    public function test_explicit_regions_actions_rules_dates_and_contact_mode_follow_legacy_state(): void
    {
        $service = $this->service(
            judging: new JudgingProgress(false, false, false, 0, false),
            contacts: [new Contact(7, 'Ada', 'Brewer', 'Organizer')],
            contactMode: ContactMode::Directory,
        );
        $view = $service->viewFor(
            Identity::fromSession(['loginUsername' => 'ada@example.test', 'userLevel' => '2']),
            $this->context(),
            1500,
        );

        self::assertTrue($view->sections->atAGlance);
        self::assertTrue($view->sections->rules);
        self::assertTrue($view->sections->entryInfo);
        self::assertTrue($view->sections->volunteers);
        self::assertFalse($view->sections->winners);
        self::assertSame('Real competition rules', $view->rules->competitionRules);
        self::assertSame('12/31/1969 6:16 PM, CST', $view->dates->registration->opens);
        self::assertSame('/index.php?section=brewer&go=account&action=edit', $view->actions->account?->url);
        self::assertSame('/index.php?section=brew&go=entry&action=add', $view->actions->entry?->url);
        self::assertSame('/index.php?section=brewer&go=account&action=edit', $view->actions->judge?->url);
        self::assertSame('/index.php?section=brewer&go=account&action=edit', $view->actions->steward?->url);
        self::assertSame('/includes/output.inc.php?section=contact&action=edit&tb=no-print&token=contact-7', $view->contacts[0]->destination);
    }

    public function test_near_capacity_is_strictly_greater_than_ninety_percent(): void
    {
        $atNinety = $this->service(
            limits: new CompetitionLimits(90, 0, 100, null, 0),
        )->viewFor(Identity::fromSession([]), $this->context(), 1500);
        $aboveNinety = $this->service(
            limits: new CompetitionLimits(91, 0, 100, null, 0),
        )->viewFor(Identity::fromSession([]), $this->context(), 1500);

        self::assertFalse($this->containsAlert($atNinety->alerts, '90 of 100'));
        self::assertTrue($this->containsAlert($aboveNinety->alerts, '91 of 100'));
    }

    public function test_hero_selector_receives_miscellaneous_and_every_selected_beverage_candidate(): void
    {
        $selector = new class implements HeroImageSelector {
            /** @var list<string> */
            public array $candidates = [];

            public function select(array $candidates): string
            {
                $this->candidates = $candidates;

                return $candidates[array_key_last($candidates)];
            }
        };
        $view = $this->service(selector: $selector)->viewFor(
            Identity::fromSession([]),
            $this->context([1, 2, 3]),
            1500,
        );

        self::assertCount(12, $selector->candidates);
        self::assertContains('misc-brussels-bottles_3000x500.jpg', $selector->candidates);
        self::assertContains('beer-on-bar_3000x500.jpg', $selector->candidates);
        self::assertContains('cider-bottles_3000x500.jpg', $selector->candidates);
        self::assertContains('mead-bottles_3000x500.jpg', $selector->candidates);
        self::assertSame('/images/mead-bottles_3000x500.jpg', $view->hero->imageUrl);
    }

    /**
     * @param list<Contact>|null $contacts
     */
    private function service(
        ?CompetitionLimits $limits = null,
        ?JudgingProgress $judging = null,
        ?BestOfShowSummary $bos = null,
        ?array $contacts = null,
        ContactMode $contactMode = ContactMode::Directory,
        ?HeroImageSelector $selector = null,
    ): LandingPageService {
        $repository = $this->createMock(LandingPageReadRepository::class);
        $repository->method('contestOverview')->willReturn(
            new ContestOverview('Fixture Competition', 'Fixture Host', null, null, null),
        );
        $repository->method('competitionWindows')->willReturn(
            new CompetitionWindows(1000, 2000, 1000, 2000, 1000, 2000, 1000, 2000, 1000, 2000),
        );
        $repository->method('competitionLimits')->willReturn(
            $limits ?? new CompetitionLimits(5, 3, 100, 80, 0),
        );
        $repository->method('judgingProgress')->willReturn(
            $judging ?? new JudgingProgress(false, false, false, 0, false),
        );
        $repository->method('locations')->willReturn(
            new CompetitionLocations(null, null, null, null, null, null, false, []),
        );
        $repository->method('competitionRules')->willReturn(
            new CompetitionRules('Real competition rules', 'Real acceptance rules'),
        );
        $repository->method('contactMode')->willReturn($contactMode);
        $repository->method('contacts')->willReturn(
            $contacts ?? [new Contact(7, 'Ada', 'Brewer', 'Organizer')],
        );
        $repository->method('sponsors')->willReturn([]);
        $repository->method('visibleArchives')->willReturn([]);
        $repository->method('winnerSummary')->willReturn(
            new WinnerSummary(WinnerMethod::Overall, 'BJCP2021', []),
        );
        $repository->method('bestOfShow')->willReturn($bos ?? new BestOfShowSummary([]));

        $contactEncoder = new class implements ContactLinkEncoder {
            public function destinationFor(int $contactId): string
            {
                return '/includes/output.inc.php?section=contact&action=edit&tb=no-print&token=contact-' . $contactId;
            }
        };

        return new LandingPageService(
            $repository,
            new LandingPageCopyAdapter(),
            $selector,
            $contactEncoder,
        );
    }

    /** @param list<int> $styleTypes */
    private function context(array $styleTypes = [1]): LandingPageContext
    {
        return new LandingPageContext(
            locale: 'en-US',
            viewerName: 'Ada',
            beverageStyleTypes: $styleTypes,
            timezone: 'America/Chicago',
            dateFormat: 1,
            timeFormat: 0,
        );
    }

    /** @param list<\Bcoem\Domain\LandingPage\Presentation\Alert> $alerts */
    private function containsAlert(array $alerts, string $needle): bool
    {
        foreach ($alerts as $alert) {
            if (str_contains($alert->message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
