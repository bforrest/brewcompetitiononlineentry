<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Bcoem\Domain\Registration\Service\CaptchaVerifier;
use Bcoem\Domain\Registration\Service\NullCaptchaVerifier;

class RegistrationContainerWiringTest extends TestCase
{
    public function test_registration_service_resolves(): void
    {
        $_SESSION['prefsCAPTCHA'] = 0;
        $container = require dirname(__DIR__, 3) . '/src/Kernel/container.php';

        $this->assertInstanceOf(RegistrationService::class, $container->get(RegistrationService::class));
        $this->assertInstanceOf(NullCaptchaVerifier::class, $container->get(CaptchaVerifier::class));
    }
}
