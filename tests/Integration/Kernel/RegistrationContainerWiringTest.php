<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Bcoem\Domain\Registration\Service\CaptchaVerifier;
use Bcoem\Domain\Registration\Service\NullCaptchaVerifier;
use Bcoem\Domain\Registration\Service\GoogleRecaptchaVerifier;

class RegistrationContainerWiringTest extends TestCase
{
    public function test_registration_service_resolves(): void
    {
        $_SESSION['prefsCAPTCHA'] = 0;
        $container = require dirname(__DIR__, 3) . '/src/Kernel/container.php';

        $this->assertInstanceOf(RegistrationService::class, $container->get(RegistrationService::class));
        $this->assertInstanceOf(NullCaptchaVerifier::class, $container->get(CaptchaVerifier::class));
    }

    public function test_captcha_verifier_resolves_to_google_recaptcha(): void
    {
        $_SESSION['prefsCAPTCHA'] = 1;
        $_SESSION['prefsGoogleAccount'] = 'public-key|secret-key|1';
        $container = require dirname(__DIR__, 3) . '/src/Kernel/container.php';

        $this->assertInstanceOf(GoogleRecaptchaVerifier::class, $container->get(CaptchaVerifier::class));
    }
}
