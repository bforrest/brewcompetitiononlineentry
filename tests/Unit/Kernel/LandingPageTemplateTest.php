<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;

final class LandingPageTemplateTest extends TestCase
{
    public function test_landing_domain_has_no_ambient_global_reads(): void
    {
        $domainDirectory = dirname(__DIR__, 3) . '/src/Domain/LandingPage';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($domainDirectory));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);
            self::assertDoesNotMatchRegularExpression(
                '/\$_SESSION|\$GLOBALS/',
                $contents,
                $file->getPathname(),
            );
        }
    }

    public function test_landing_templates_have_no_ambient_state_or_dynamic_includes(): void
    {
        $templateDirectory = dirname(__DIR__, 3) . '/templates/LandingPage';
        $expectedPartials = [
            'alerts.php',
            'registration.php',
            'at-a-glance.php',
            'rules.php',
            'entry-info.php',
            'winners.php',
            'contacts.php',
            'sponsors.php',
            'login.php',
            'archives.php',
        ];

        self::assertDirectoryExists($templateDirectory);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templateDirectory));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);
            self::assertDoesNotMatchRegularExpression('/\$_SESSION|\$GLOBALS|mysqli_/', $contents, $file->getPathname());

            if ($file->getFilename() === 'home.php') {
                foreach ($expectedPartials as $partial) {
                    self::assertStringContainsString("require __DIR__ . '/partials/{$partial}';", $contents);
                }
                self::assertSame(10, preg_match_all('/\brequire\b/', $contents));
                continue;
            }

            self::assertDoesNotMatchRegularExpression('/\b(?:require|include)(?:_once)?\b/', $contents, $file->getPathname());
        }
    }
}
