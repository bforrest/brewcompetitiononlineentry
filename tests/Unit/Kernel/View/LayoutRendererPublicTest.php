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

        $html = $renderer->public('Register', $fixtureTemplate, ['message' => 'hello from fixture']);

        $this->assertStringContainsString('/css/common.min.css', $html);
        $this->assertStringContainsString('/css/default.min.css', $html);
        $this->assertStringContainsString('>Register</a>', $html);
        $this->assertStringContainsString('>Log in</a>', $html);
        $this->assertStringContainsString('<h1>Register</h1>', $html);
        $this->assertStringContainsString('<p class="fixture-content">hello from fixture</p>', $html);
        $this->assertStringContainsString('<footer', $html);
    }
}
