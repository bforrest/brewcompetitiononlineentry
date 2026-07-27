<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\View;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Presentation\Alert;
use Bcoem\Domain\LandingPage\Presentation\AlertLevel;
use Bcoem\Domain\LandingPage\Presentation\HeroPresentation;
use Bcoem\Domain\LandingPage\Presentation\LandingPageCopy;
use Bcoem\Domain\LandingPage\Presentation\LandingPageLinks;
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
        self::assertStringContainsString('aria-label="Primary navigation"', $html);
        self::assertStringContainsString('data-bs-toggle="collapse"', $html);
        self::assertStringContainsString('data-bs-toggle="offcanvas"', $html);
        self::assertStringContainsString('>Past Winners</button>', $html);
        self::assertStringContainsString('data-bs-toggle="modal" data-bs-target="#login-modal"', $html);
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
        self::assertStringContainsString('class="link-light" href="http://www.brewingcompetitions.com"', $html);
        self::assertStringContainsString('<section id="volunteers"', $html);
        self::assertStringContainsString('Entry status</dt><dd class="col-sm-8">Open</dd>', $html);
        self::assertStringContainsString('Drop-off status</dt><dd class="col-sm-8">Upcoming</dd>', $html);
        self::assertStringContainsString('Shipping status</dt><dd class="col-sm-8">Open</dd>', $html);
        self::assertStringContainsString('datetime="2024-12-03T04:26:40+00:00"', $html);
        self::assertStringContainsString('December 3, 2024 4:26 AM UTC', $html);
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

    private function landingView(bool $loggedIn = false): LandingPageViewModel
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
            new JudgingProgress(false, false, false, 0),
            false,
            new CompetitionLocations('Fixture Shipping', '123 Brew Street', 'Awards after judging.', 'Fixture Hall', '456 Malt Ave', 1_733_200_000),
            [new Alert(AlertLevel::Info, '<script>alert(1)</script>', 'Learn more', '/#rules')],
            [],
            [],
            [new Archive('2025', 0, '2021')],
            new WinnerSummary(WinnerMethod::Overall, '2021', [
                new WinnerRow('Best of Show', 1, 1, 'Alex Brewer', null, 'Fixture Stout', 'Stout', null, null, 42.5),
            ]),
            new HeroPresentation('/user_images/hero.jpg', 'Fixture Invitational', 'Great beer, good company.'),
            new LandingPageLinks('/register', '/index.php?section=login', '/includes/process.inc.php?section=logout&action=logout', '/index.php?section=list', '/#contact', '/#sponsors', 'https://fixture.example.test', '/results.pdf', '/results.html'),
            new LandingPageCopy('Register', 'Log In', 'Log Out', 'Rules', 'Volunteers', 'Entry Info', 'Contact', 'Sponsors', 'Officials', 'Results', 'Registration opens soon.', 'Registration is open.', 'Registration is closed.', 'Judge registration is open.', 'Entry capacity is full.', 'Entry capacity is nearly full.', 'Paid entry capacity is full.', 'Winners will be posted soon.'),
        );
    }
}
