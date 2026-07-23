<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\View;

use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;

class LayoutRendererTest extends TestCase
{
    private LayoutRenderer $renderer;
    private string $fixtureTemplate;

    protected function setUp(): void
    {
        unset($_SESSION['prefsTheme']);
        $this->renderer = new LayoutRenderer();
        $this->fixtureTemplate = __DIR__ . '/fixtures/fixture-template.php';
    }

    public function test_admin_renders_inner_content_verbatim(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'hello from fixture']);

        $this->assertStringContainsString('<p class="fixture-content">hello from fixture</p>', $html);
    }

    public function test_admin_includes_title_in_page_header(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('<h1>Judging Tables</h1>', $html);
    }

    public function test_admin_shows_identity_username_and_logout_link(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('admin@test.local', $html);
        $this->assertStringContainsString('logout', $html);
    }

    public function test_admin_includes_sidebar(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('class="sidebar', $html);
    }

    public function test_authenticated_omits_sidebar(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);
        $html = $this->renderer->authenticated($identity, 'Judging Scoresheet', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringNotContainsString('class="sidebar', $html);
    }

    public function test_authenticated_still_includes_nav_and_footer(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);
        $html = $this->renderer->authenticated($identity, 'Judging Scoresheet', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('<footer', $html);
    }

    public function test_default_theme_links_default_css(): void
    {
        unset($_SESSION['prefsTheme']);
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'T', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('/css/common.min.css', $html);
        $this->assertStringContainsString('/css/default.min.css', $html);
        $this->assertStringContainsString('bootstrap/3.3.7/css/bootstrap.min.css', $html);
        $this->assertStringNotContainsString('bootstrap@5.3.3/dist/css/bootstrap.min.css', $html);
    }

    public function test_session_theme_preference_overrides_default_css(): void
    {
        $_SESSION['prefsTheme'] = 'bruxellensis';
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'T', 'judging', $this->fixtureTemplate, ['message' => 'x']);
        unset($_SESSION['prefsTheme']);

        $this->assertStringContainsString('/css/bruxellensis.min.css', $html);
    }

    public function test_missing_template_throws(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does-not-exist\.php/');

        $this->renderer->admin($identity, 'T', 'judging', __DIR__ . '/fixtures/does-not-exist.php', []);
    }
}
