<?php

declare(strict_types=1);

namespace Bcoem\Tests\Unit\Domain\Export\Command;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use PHPUnit\Framework\TestCase;

class GenerateExportCommandTest extends TestCase
{
    public function testCommandCreatesWithValidData(): void
    {
        $command = new GenerateExportCommand('csv', 'all', 'default', '2024_results');

        $this->assertSame('csv', $command->format);
        $this->assertSame('all', $command->filter);
        $this->assertSame('default', $command->view);
        $this->assertSame('2024_results', $command->archiveSuffix);
    }

    public function testCommandAllowsNullArchiveSuffix(): void
    {
        $command = new GenerateExportCommand('csv', 'all');

        $this->assertNull($command->archiveSuffix);
        $this->assertSame('default', $command->view);
    }

    public function testCommandHasDefaultView(): void
    {
        $command = new GenerateExportCommand('html', 'paid');

        $this->assertSame('default', $command->view);
    }
}

