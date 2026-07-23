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
}
