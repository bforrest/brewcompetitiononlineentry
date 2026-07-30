<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\View;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\BestOfShowSummary;
use Bcoem\Domain\LandingPage\Model\BestOfShowWinner;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionRules;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\DropoffLocation;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\Alert;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\HeroPresentation;
use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;
use Bcoem\Domain\LandingPage\Presentation\LandingAction;
use Bcoem\Domain\LandingPage\Presentation\LandingPageActions;
use Bcoem\Domain\LandingPage\Presentation\LandingPageDateRange;
use Bcoem\Domain\LandingPage\Presentation\LandingPageDates;
use Bcoem\Domain\LandingPage\Presentation\LandingPageLinks;
use Bcoem\Domain\LandingPage\Presentation\LandingPageSections;
use Bcoem\Domain\LandingPage\Presentation\LandingPageViewModel;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use Bcoem\Kernel\View\LayoutRenderer;
use PHPUnit\Framework\TestCase;

class LayoutRendererPublicTest extends TestCase
{
    public function test_public_renders_anonymous_bootstrap_chrome_and_content(): void
    {
        unset($_SESSION['prefsTheme']);
        $renderer = new LayoutRenderer();
        $fixtureTemplate = __DIR__ . '/fixtures/fixture-template.php';

        $html = $renderer->public(
            'Register',
            'Fixture Invitational',
            $fixtureTemplate,
            ['message' => 'hello from fixture'],
        );

        $this->assertStringNotContainsString('bootstrap@5.3.3/dist/css/bootstrap.min.css', $html);
        $this->assertStringContainsString('livecanvas-team/ninjabootstrap', $html);
        $this->assertStringContainsString('font-awesome/6.7.2/css/all.min.css', $html);
        $this->assertStringContainsString('tom-select.bootstrap5.min.css', $html);
        $this->assertStringContainsString('zxcvbn/4.4.2/zxcvbn.js', $html);
        $this->assertStringContainsString('pwstrength-bootstrap/3.1.3/pwstrength-bootstrap.min.js', $html);
        $this->assertStringContainsString('/css/common-3.min.css', $html);
        $this->assertStringContainsString('/css/default-3.min.css', $html);
        $this->assertStringNotContainsString('bootstrap/3.3.7/css/bootstrap.min.css', $html);
        $this->assertStringContainsString('>Rules</a>', $html);
        $this->assertStringContainsString('fas fa-home me-2', $html);
        $this->assertStringContainsString('<div class="container-fluid">', $html);
        $this->assertStringContainsString('<div id="sticky-home" class="contains-link d-print-none">', $html);
        $this->assertStringContainsString('href="#home"', $html);
        $this->assertStringContainsString('>Volunteers</a>', $html);
        $this->assertStringContainsString('>Entry Info</a>', $html);
        $this->assertStringContainsString('>Contact</a>', $html);
        $this->assertStringContainsString('>Log In</a>', $html);
        $this->assertStringContainsString('<div id="salutation" class="text-light bg-black pt-4 pb-3 d-print-none">', $html);
        $this->assertStringContainsString('<section class="container-xxl">', $html);
        $this->assertStringContainsString('<h1 class="fw-bold animate__animated animate__fadeInDown">Fixture Invitational</h1>', $html);
        $this->assertStringContainsString('class="container-xxl"', $html);
        $this->assertStringNotContainsString('<h1>Register</h1>', $html);
        $this->assertStringContainsString('<p class="fixture-content">hello from fixture</p>', $html);
        $this->assertStringContainsString('<footer class="site-footer bg-dark text-light', $html);
    }

    public function test_landing_renders_typed_model_in_bootstrap_five_chrome(): void
    {
        $renderer = new LayoutRenderer();
        $template = dirname(__DIR__, 4) . '/templates/LandingPage/home.php';

        $html = $renderer->landing($this->landingView(), $template);

        self::assertStringContainsString('<main id="main-content"', $html);
        self::assertStringContainsString('<nav id="site-nav"', $html);
        self::assertStringContainsString('aria-labelledby="landing-title"', $html);
        self::assertStringNotContainsString('aria-label="Primary navigation"', $html);
        self::assertStringContainsString('data-bs-toggle="collapse"', $html);
        self::assertStringContainsString('data-bs-toggle="offcanvas"', $html);
        self::assertStringContainsString('>Archived Champions</button>', $html);
        self::assertStringContainsString('>Archived Champions</h2>', $html);
        self::assertStringContainsString('data-bs-toggle="modal" data-bs-target="#login-modal"', $html);
        self::assertStringContainsString(
            'class="alert-link" href="#login-modal" data-bs-toggle="modal" data-bs-target="#login-modal"',
            $html,
        );
        self::assertStringContainsString('id="login-modal"', $html);
        self::assertStringContainsString('aria-labelledby="login-modal-label"', $html);
        self::assertStringContainsString('name="loginUsername"', $html);
        self::assertStringContainsString('name="loginPassword"', $html);
        self::assertStringContainsString('action="/includes/process.inc.php?section=login&amp;action=login"', $html);
        self::assertStringNotContainsString('data-toggle=', $html);
        self::assertStringNotContainsString('navbar-default', $html);
        self::assertStringNotContainsString('<script>alert(', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        self::assertStringContainsString('Fixture Brewers', $html);
        self::assertStringContainsString('href="https://fixture.example.test"', $html);
        self::assertStringContainsString('src="/user_images/fixture-logo.svg"', $html);
        self::assertStringContainsString('Chicago, IL', $html);
        self::assertStringNotContainsString('class="text-light-emphasis"', $html);
        self::assertStringContainsString(
            'class="link-light d-inline-block py-1" href="http://www.brewingcompetitions.com"',
            $html,
        );
        self::assertStringContainsString('<section id="volunteers"', $html);
        self::assertStringContainsString('Entry status</dt><dd class="col-sm-8">Open</dd>', $html);
        self::assertStringContainsString('Drop-off status</dt><dd class="col-sm-8">Upcoming</dd>', $html);
        self::assertStringContainsString('Shipping status</dt><dd class="col-sm-8">Open</dd>', $html);
        self::assertStringContainsString('<time>08/02/2026 2:00 PM, CDT</time>', $html);
        self::assertStringNotContainsString('December 3, 2024 4:26 AM UTC', $html);
        self::assertStringNotContainsString('href="/#sponsors">Sponsors</a>', $html);
    }

    public function test_landing_uses_the_typed_account_link_for_authenticated_viewers(): void
    {
        $renderer = new LayoutRenderer();
        $template = dirname(__DIR__, 4) . '/templates/LandingPage/home.php';

        $html = $renderer->landing($this->landingView(true), $template);

        self::assertStringContainsString('Hello, Ada Brewer', $html);
        self::assertStringContainsString('href="/index.php?section=list">Account</a>', $html);
        self::assertStringNotContainsString('name="loginUsername"', $html);
    }

    public function test_landing_url_encodes_special_characters_in_archive_suffixes(): void
    {
        $renderer = new LayoutRenderer();
        $template = dirname(__DIR__, 4) . '/templates/LandingPage/home.php';

        $html = $renderer->landing($this->landingView(archiveSuffix: '2025 & finals'), $template);

        self::assertStringContainsString(
            'href="/index.php?section=past-winners&amp;go=2025%20%26%20finals"',
            $html,
        );
    }

    public function test_landing_renders_rules_dropoffs_actions_dates_contacts_and_best_of_show(): void
    {
        $renderer = new LayoutRenderer();
        $template = dirname(__DIR__, 4) . '/templates/LandingPage/home.php';

        $html = $renderer->landing($this->landingView(), $template);

        self::assertStringContainsString('Competition rules for everyone.', $html);
        self::assertStringContainsString('Two plain bottles.', $html);
        self::assertStringContainsString('Fixture Bottle Shop', $html);
        self::assertStringContainsString('07/24/2026 9:00 AM, CDT', $html);
        self::assertStringContainsString(
            'href="/index.php?section=brew&amp;go=entry&amp;action=add"',
            $html,
        );
        self::assertStringContainsString(
            'href="/index.php?section=register&amp;go=steward"',
            $html,
        );
        self::assertStringContainsString(
            'href="/includes/output.inc.php?section=contact&amp;action=edit&amp;tb=no-print&amp;token=fixture"',
            $html,
        );
        self::assertStringNotContainsString('ada@example.test', $html);
        self::assertStringContainsString('Best of Show', $html);
        self::assertStringContainsString('Winning Mead', $html);
    }

    public function test_landing_hero_includes_a_welcome_bar_with_contest_and_host_names(): void
    {
        $renderer = new LayoutRenderer();
        $template = dirname(__DIR__, 4) . '/templates/LandingPage/home.php';

        $html = $renderer->landing($this->landingView(), $template);

        self::assertStringContainsString(
            'Thank you for your interest in Fixture Invitational organized by',
            $html,
        );
        self::assertStringContainsString('Fixture Brewers', $html);
        self::assertMatchesRegularExpression('/landing-hero[^"]*"[^>]*style="min-height: 22rem/', $html);
    }

    private function landingView(bool $loggedIn = false, string $archiveSuffix = '2025'): LandingPageViewModel
    {
        return new LandingPageViewModel(
            new ContestOverview('Fixture Invitational', 'Fixture Brewers', 'https://fixture.example.test', 'Chicago, IL', '/user_images/fixture-logo.svg'),
            $loggedIn,
            $loggedIn ? 'Ada Brewer' : null,
            WindowStatus::Open,
            WindowStatus::Open,
            WindowStatus::Upcoming,
            WindowStatus::Upcoming,
            WindowStatus::Open,
            new CompetitionLimits(20, 18, 30, 25, 3),
            new JudgingProgress(false, false, true, 0, true),
            true,
            new CompetitionLocations(
                'Fixture Shipping',
                '123 Brew Street',
                'Awards after judging.',
                'Fixture Hall',
                '456 Malt Ave',
                1_733_200_000,
                true,
                [new DropoffLocation('Fixture Bottle Shop', '789 Hops Road', null, null, 'Rear entrance')],
            ),
            [
                new Alert(AlertLevel::Info, '<script>alert(1)</script>', 'Learn more', '/#rules'),
                new Alert(AlertLevel::Warning, 'Registration closed.', 'Log In', '#login-modal'),
            ],
            [new Contact(
                7,
                'Ada',
                'Brewer',
                'Organizer',
                '/includes/output.inc.php?section=contact&action=edit&tb=no-print&token=fixture',
            )],
            [],
            [new Archive($archiveSuffix, 0, '2021')],
            new WinnerSummary(WinnerMethod::Overall, '2021', [
                new WinnerRow('Best of Show', 1, 1, 'Alex Brewer', null, 'Fixture Stout', 'Stout', null, null, 42.5),
            ]),
            new HeroPresentation('/user_images/hero.jpg', 'Fixture Invitational', 'Great beer, good company.'),
            new LandingPageLinks('/register', '/index.php?section=login', '/includes/process.inc.php?section=logout&action=logout', '/index.php?section=list', '/#contact', '/#sponsors', 'https://fixture.example.test', '/results.pdf', '/results.html'),
            new LandingPageCopy(
                register: 'Register',
                login: 'Log In',
                logout: 'Log Out',
                rules: 'Rules',
                volunteers: 'Volunteers',
                entryInfo: 'Entry Info',
                contact: 'Contact',
                sponsors: 'Sponsors',
                officials: 'Officials',
                results: 'Results',
                pastWinners: 'Archived Champions',
                upcomingMessage: 'Registration opens soon.',
                openMessage: 'Registration is open.',
                closedMessage: 'Registration is closed.',
                judgeOpenMessage: 'Judge registration is open.',
                entryLimitMessage: 'Entry capacity is full.',
                nearLimitMessage: 'Entry capacity is nearly full.',
                paidEntryLimitMessage: 'Paid entry capacity is full.',
                winnerDelayMessage: 'Winners will be posted soon.',
            ),
            new CompetitionRules('Competition rules for everyone.', 'Two plain bottles.'),
            new LandingPageDates(
                new LandingPageDateRange('07/24/2026 9:00 AM, CDT', '07/31/2026 5:00 PM, CDT', 1, 2),
                new LandingPageDateRange('07/24/2026 9:00 AM, CDT', '07/31/2026 5:00 PM, CDT', 1, 2),
                new LandingPageDateRange('07/24/2026 9:00 AM, CDT', '07/31/2026 5:00 PM, CDT', 1, 2),
                new LandingPageDateRange(null, null, null, null),
                new LandingPageDateRange(null, null, null, null),
                '08/02/2026 2:00 PM, CDT',
            ),
            new LandingPageActions(
                new LandingAction('Register', '/index.php?section=register&go=entrant'),
                new LandingAction('Add entry', '/index.php?section=brew&go=entry&action=add'),
                new LandingAction('Register as a judge', '/index.php?section=register&go=judge'),
                new LandingAction('Register as a steward', '/index.php?section=register&go=steward'),
            ),
            new LandingPageSections(true, true, true, true, true, true, false),
            ContactMode::Directory,
            new BestOfShowSummary([
                new BestOfShowWinner(
                    'Mead',
                    1,
                    'Ada Brewer',
                    null,
                    'Winning Mead',
                    'M1A: Dry Mead',
                    'Fixture Club',
                ),
            ]),
        );
    }
}
