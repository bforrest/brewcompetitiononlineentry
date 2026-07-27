<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\LandingPage\Model;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\Alert;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\HeroPresentation;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;
use Bcoem\Domain\LandingPage\Presentation\LandingPageLinks;
use Bcoem\Domain\LandingPage\Presentation\LandingPageViewModel;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use PHPUnit\Framework\TestCase;

final class LandingPageModelTest extends TestCase
{
    public function test_contest_overview_rejects_blank_title(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ContestOverview('', 'Host Club', null, null, null);
    }

    public function test_landing_links_reject_unsafe_external_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageLinks(
            register: '/register',
            login: '/index.php?section=login',
            logout: '/includes/process.inc.php?section=logout',
            account: 'javascript:alert(1)',
            contact: '/#contact',
            sponsors: '/#sponsors',
            hostWebsite: 'https://host.example.test',
            resultsPdf: '/includes/output.inc.php?section=export-results&view=pdf',
            resultsHtml: '/includes/output.inc.php?section=export-results&view=html',
        );
    }

    public function test_landing_links_expose_a_validated_account_url(): void
    {
        $links = new LandingPageLinks(
            register: '/register',
            login: '/index.php?section=login',
            logout: '/includes/process.inc.php?section=logout&action=logout',
            account: '/index.php?section=list',
            contact: '/#contact',
            sponsors: '/#sponsors',
            hostWebsite: null,
            resultsPdf: '/results.pdf',
            resultsHtml: '/results.html',
        );

        self::assertSame('/index.php?section=list', $links->account);
    }

    public function test_url_bearing_models_accept_relative_http_and_https_urls(): void
    {
        $contest = new ContestOverview('Contest', 'Host Club', 'https://host.example', 'Chicago', '/logo.svg');
        $sponsor = new Sponsor('Sponsor', 'http://sponsor.example', '/sponsor.png', 'Supporter', 'Chicago', 1);
        $alert = new Alert(AlertLevel::Info, 'Message', 'More', '/more');
        $hero = new HeroPresentation('/images/hero.jpg', 'Heading', 'Subheading');

        self::assertSame('https://host.example', $contest->hostWebsite);
        self::assertSame('http://sponsor.example', $sponsor->websiteUrl);
        self::assertSame('/more', $alert->linkUrl);
        self::assertSame('/images/hero.jpg', $hero->imageUrl);
    }

    public function test_url_bearing_models_accept_safe_same_document_fragments(): void
    {
        $alert = new Alert(AlertLevel::Info, 'Message', 'Log In', '#login-modal');

        self::assertSame('#login-modal', $alert->linkUrl);
    }

    /**
     * @dataProvider unsafeUrlCases
     * @param callable(string): object $construct
     */
    public function test_url_bearing_models_reject_unsafe_urls(string $url, callable $construct): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $construct($url);
    }

    /** @return iterable<string, array{string, callable(string): object}> */
    public static function unsafeUrlCases(): iterable
    {
        $constructors = [
            'contest' => static fn (string $url): ContestOverview => new ContestOverview(
                'Contest',
                'Host',
                $url,
                null,
                null,
            ),
            'sponsor' => static fn (string $url): Sponsor => new Sponsor(
                'Sponsor',
                $url,
                null,
                null,
                null,
                1,
            ),
            'alert' => static fn (string $url): Alert => new Alert(AlertLevel::Info, 'Message', 'More', $url),
            'hero' => static fn (string $url): HeroPresentation => new HeroPresentation($url, 'Heading', 'Subheading'),
            'links' => static fn (string $url): LandingPageLinks => new LandingPageLinks(
                '/register',
                '/login',
                '/logout',
                '/account',
                '/#contact',
                '/#sponsors',
                $url,
                '/results.pdf',
                '/results.html',
            ),
        ];

        foreach ($constructors as $name => $construct) {
            yield $name . ' rejects scheme relative' => ['//evil.example/path', $construct];
            yield $name . ' rejects triple slash' => ['///evil.example/path', $construct];
            yield $name . ' rejects backslash' => ['/\\evil.example/path', $construct];
            yield $name . ' rejects malformed HTTP' => ['https://', $construct];
            yield $name . ' rejects unsafe scheme' => ['javascript:alert(1)', $construct];
        }
    }

    public function test_url_bearing_models_accept_case_insensitive_http_schemes(): void
    {
        $links = new LandingPageLinks(
            '/register',
            '/login',
            '/logout',
            '/account',
            '/#contact',
            '/#sponsors',
            'HTTPS://host.example/path',
            '/results.pdf',
            '/results.html',
        );

        self::assertSame('HTTPS://host.example/path', $links->hostWebsite);
    }

    public function test_context_rejects_unsupported_locale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageContext('fr-FR', null, []);
    }

    public function test_context_rejects_invalid_beverage_style_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageContext('en-US', null, [4]);
    }

    /**
     * @dataProvider invalidBeverageStyleTypeLists
     * @param array<mixed> $types
     */
    public function test_context_rejects_non_integer_or_non_list_beverage_style_types(array $types): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageContext('en-US', null, $types);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidBeverageStyleTypeLists(): iterable
    {
        yield 'numeric string' => [['1']];
        yield 'null' => [[null]];
        yield 'boolean' => [[true]];
        yield 'sparse keys' => [[1 => 1]];
    }

    public function test_view_model_accepts_typed_lists_and_scalar_read_models(): void
    {
        $view = new LandingPageViewModel(
            contest: new ContestOverview('Contest', 'Host Club', null, null, null),
            loggedIn: false,
            viewerName: null,
            registrationStatus: WindowStatus::Open,
            entryStatus: WindowStatus::Open,
            judgeStatus: WindowStatus::Upcoming,
            dropoffStatus: WindowStatus::Open,
            shippingStatus: WindowStatus::Open,
            capacity: new CompetitionLimits(2, 1, 100, 100, 90),
            judging: new JudgingProgress(false, false, false, 0),
            winnerResultsVisible: false,
            locations: new CompetitionLocations('Ship To', '123 Shipping St', 'Awards', 'Awards Hall', '456 Awards Ave', 2000),
            alerts: [new Alert(AlertLevel::Info, 'Message')],
            contacts: [new Contact(7, 'Ada', 'Brewer', 'Organizer', '/contact/token')],
            sponsors: [new Sponsor('Sponsor', null, null, null, null, 1)],
            archives: [new Archive('2025', 0, 'BJCP2021')],
            winners: new WinnerSummary(
                WinnerMethod::Overall,
                'BJCP2021',
                [new WinnerRow('Table 1', 2, 1, 'Ada Brewer', null, 'Winning Entry', '1A: Style', null, 'Club', 42.5)],
            ),
            hero: new HeroPresentation('/hero.jpg', 'Heading', 'Subheading'),
            links: $this->links(),
            copy: $this->copy(),
        );

        self::assertSame('Contest', $view->contest->name);
        self::assertSame('Ada', $view->contacts[0]->firstName);
        self::assertSame(2000, $view->locations->awardsAt);
        self::assertSame(WinnerMethod::Overall, $view->winners->method);
        self::assertSame('Winning Entry', $view->winners->rows[0]->entryName);
        self::assertSame('Near capacity', $view->copy->nearLimitMessage);
        self::assertSame('Paid capacity', $view->copy->paidEntryLimitMessage);
    }

    public function test_view_model_rejects_a_non_alert_list_member(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageViewModel(
            contest: new ContestOverview('Contest', 'Host Club', null, null, null),
            loggedIn: false,
            viewerName: null,
            registrationStatus: WindowStatus::Open,
            entryStatus: WindowStatus::Open,
            judgeStatus: WindowStatus::Upcoming,
            dropoffStatus: WindowStatus::Open,
            shippingStatus: WindowStatus::Open,
            capacity: new CompetitionLimits(2, 1, 100, 100, 90),
            judging: new JudgingProgress(false, false, false, 0),
            winnerResultsVisible: false,
            locations: new CompetitionLocations(null, null, null, null, null, null),
            alerts: ['not an alert'],
            contacts: [],
            sponsors: [],
            archives: [],
            winners: new WinnerSummary(WinnerMethod::Overall, 'BJCP2021', []),
            hero: new HeroPresentation('/hero.jpg', 'Heading', 'Subheading'),
            links: $this->links(),
            copy: $this->copy(),
        );
    }

    public function test_winner_summary_rejects_untyped_rows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WinnerSummary(WinnerMethod::Category, 'BJCP2021', ['not a winner row']);
    }

    private function links(): LandingPageLinks
    {
        return new LandingPageLinks('/register', '/login', '/logout', '/account', '/#contact', '/#sponsors', null, '/results.pdf', '/results.html');
    }

    private function copy(): LandingPageCopy
    {
        return new LandingPageCopy(
            'Register', 'Login', 'Logout', 'Rules', 'Volunteers', 'Entry info', 'Contact', 'Sponsors',
            'Officials', 'Results', 'Past winners', 'Upcoming', 'Open', 'Closed', 'Judge open', 'Entry limit',
            'Near capacity', 'Paid capacity', 'Winner delay',
        );
    }
}
