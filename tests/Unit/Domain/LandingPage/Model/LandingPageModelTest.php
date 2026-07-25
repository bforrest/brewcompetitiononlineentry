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
            contact: '/#contact',
            sponsors: '/#sponsors',
            hostWebsite: 'javascript:alert(1)',
            resultsPdf: '/includes/output.inc.php?section=export-results&view=pdf',
            resultsHtml: '/includes/output.inc.php?section=export-results&view=html',
        );
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

    public function test_url_bearing_models_reject_unsafe_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Sponsor('Sponsor', 'javascript:alert(1)', null, null, null, 1);
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
            locations: new CompetitionLocations('Ship To', '123 Shipping St', 'Awards', 'Awards Hall', '456 Awards Ave', 2000),
            alerts: [new Alert(AlertLevel::Info, 'Message')],
            contacts: [new Contact('Ada', 'Brewer', 'Organizer', 'ada@example.test')],
            sponsors: [new Sponsor('Sponsor', null, null, null, null, 1)],
            archives: [new Archive('2025', 0, 1)],
            winners: new WinnerSummary(0, 1, 0),
            hero: new HeroPresentation('/hero.jpg', 'Heading', 'Subheading'),
            links: $this->links(),
            copy: $this->copy(),
        );

        self::assertSame('Contest', $view->contest->name);
        self::assertSame('Ada', $view->contacts[0]->firstName);
        self::assertSame(2000, $view->locations->awardsAt);
        self::assertSame(0, $view->winners->method);
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
            locations: new CompetitionLocations(null, null, null, null, null, null),
            alerts: ['not an alert'],
            contacts: [],
            sponsors: [],
            archives: [],
            winners: new WinnerSummary(0, 1, 0),
            hero: new HeroPresentation('/hero.jpg', 'Heading', 'Subheading'),
            links: $this->links(),
            copy: $this->copy(),
        );
    }

    private function links(): LandingPageLinks
    {
        return new LandingPageLinks('/register', '/login', '/logout', '/#contact', '/#sponsors', null, '/results.pdf', '/results.html');
    }

    private function copy(): LandingPageCopy
    {
        return new LandingPageCopy(
            'Register', 'Login', 'Logout', 'Rules', 'Volunteers', 'Entry info', 'Contact', 'Sponsors',
            'Officials', 'Results', 'Upcoming', 'Open', 'Closed', 'Judge open', 'Entry limit', 'Winner delay',
        );
    }
}
