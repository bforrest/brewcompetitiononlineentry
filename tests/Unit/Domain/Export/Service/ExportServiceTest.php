<?php

declare(strict_types=1);

namespace Bcoem\Tests\Unit\Domain\Export\Service;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Exception\InvalidExportFilterException;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\Repository\ParticipantExportRepository;
use Bcoem\Domain\Export\Repository\JudgingExportRepository;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportValidationService;
use Bcoem\Domain\Export\ValueObject\ExportFilter;
use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;
use PHPUnit\Framework\TestCase;

class ExportServiceTest extends TestCase
{
    private ExportService $service;
    private BrewingExportRepository $brewingRepo;
    private ParticipantExportRepository $participantRepo;
    private JudgingExportRepository $judgingRepo;
    private ExportValidationService $validation;

    protected function setUp(): void
    {
        $this->brewingRepo = $this->createMock(BrewingExportRepository::class);
        $this->participantRepo = $this->createMock(ParticipantExportRepository::class);
        $this->judgingRepo = $this->createMock(JudgingExportRepository::class);
        $this->validation = $this->createMock(ExportValidationService::class);

        $this->service = new ExportService(
            $this->brewingRepo,
            $this->participantRepo,
            $this->judgingRepo,
            $this->validation
        );
    }

    public function testExecuteGeneratesCsvExport(): void
    {
        $command = new GenerateExportCommand('csv', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testuser', 'userLevel' => '0']); // SuperAdmin

        $this->brewingRepo->expects($this->once())
            ->method('getAllEntries')
            ->willReturn([
                ['id' => 1, 'brewName' => 'Test Brew', 'brewStyle' => 'IPA'],
                ['id' => 2, 'brewName' => 'Another Brew', 'brewStyle' => 'Stout'],
            ]);

        $report = $this->service->execute($command, $user);

        $this->assertSame(ExportFormat::CSV, $report->format());
        $this->assertSame(ExportFilter::ALL, $report->filter());
        $this->assertCount(2, $report->rows());
    }

    public function testExecuteValidatesCommand(): void
    {
        $this->validation->expects($this->once())
            ->method('validateCommand')
            ->willThrowException(new InvalidExportFilterException('Invalid filter'));

        $this->expectException(InvalidExportFilterException::class);

        $command = new GenerateExportCommand('invalid', 'invalid', 'default');
        $user = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '0']);

        $this->service->execute($command, $user);
    }

    public function testExecuteExtractsColumnHeaders(): void
    {
        $command = new GenerateExportCommand('csv', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '0']);

        $this->brewingRepo->expects($this->once())
            ->method('getAllEntries')
            ->willReturn([
                ['id' => 1, 'brewName' => 'Test', 'brewStyle' => 'IPA'],
            ]);

        $report = $this->service->execute($command, $user);

        $this->assertContains('id', $report->columnHeaders());
        $this->assertContains('brewName', $report->columnHeaders());
        $this->assertContains('brewStyle', $report->columnHeaders());
    }
}
