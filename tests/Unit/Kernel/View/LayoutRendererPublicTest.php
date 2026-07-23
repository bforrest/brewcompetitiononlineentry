<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\View;

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

        $this->assertStringContainsString('/css/common.min.css', $html);
        $this->assertStringContainsString('/css/default.min.css', $html);
        $this->assertStringContainsString('>Rules</a>', $html);
        $this->assertStringContainsString('glyphicon glyphicon-home', $html);
        $this->assertStringContainsString('>Volunteers</a>', $html);
        $this->assertStringContainsString('>Entry Info</a>', $html);
        $this->assertStringContainsString('>Contact</a>', $html);
        $this->assertStringContainsString('>Log In</a>', $html);
        $this->assertStringContainsString('<h1>Fixture Invitational</h1>', $html);
        $this->assertStringNotContainsString('<h1>Register</h1>', $html);
        $this->assertStringContainsString('<p class="fixture-content">hello from fixture</p>', $html);
        $this->assertStringContainsString('<footer', $html);
    }
}
