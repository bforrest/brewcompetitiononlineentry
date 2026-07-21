<?php

declare(strict_types=1);

namespace Bcoem\Tests\Integration\Domain\Export\Service;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\Repository\ParticipantExportRepository;
use Bcoem\Domain\Export\Repository\JudgingExportRepository;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportValidationService;
use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class ExportServiceIntegrationTest extends TestCase
{
    private ExportService $service;
    private Connection $connection;
    private \mysqli $mysqli;

    protected function setUp(): void
    {
        $this->mysqli = $GLOBALS['connection'] ?? null;

        if (!$this->mysqli instanceof \mysqli) {
            $this->markTestSkipped('Database connection not available');
        }

        $this->connection = new Connection($this->mysqli);

        $brewingRepo = new BrewingExportRepository($this->connection);
        $participantRepo = new ParticipantExportRepository($this->connection);
        $judgingRepo = new JudgingExportRepository($this->connection);

        $validator = new RecursiveValidator(
            new ClassMetadataFactory(new AttributeLoader()),
            []
        );
        $validation = new ExportValidationService($validator);

        $this->service = new ExportService(
            $brewingRepo,
            $participantRepo,
            $judgingRepo,
            $validation
        );
    }

    public function testExecuteGeneratesCsvExportWithRealData(): void
    {
        // Check if database has data
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $this->markTestSkipped('No brewing data in database');
        }

        // Arrange
        $command = new GenerateExportCommand('csv', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testadmin', 'userLevel' => '0']);

        // Act
        $report = $this->service->execute($command, $user);

        // Assert
        $this->assertSame(ExportFormat::CSV, $report->format());
        $this->assertGreaterThan(0, $report->rowCount());
        $this->assertNotEmpty($report->columnHeaders());
    }

    public function testExecuteGeneratesHtmlExport(): void
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $this->markTestSkipped('No brewing data in database');
        }

        // Arrange
        $command = new GenerateExportCommand('html', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testadmin', 'userLevel' => '0']);

        // Act
        $report = $this->service->execute($command, $user);

        // Assert
        $this->assertSame(ExportFormat::HTML, $report->format());
        $this->assertGreaterThan(0, $report->rowCount());
    }

    public function testExecuteGeneratesXmlExport(): void
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $this->markTestSkipped('No brewing data in database');
        }

        // Arrange
        $command = new GenerateExportCommand('xml', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testadmin', 'userLevel' => '0']);

        // Act
        $report = $this->service->execute($command, $user);

        // Assert
        $this->assertSame(ExportFormat::XML, $report->format());
        $this->assertGreaterThan(0, $report->rowCount());
    }

    public function testExecuteRespectsPaidFilter(): void
    {
        // Check database
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing WHERE brewPaid = 1");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $this->markTestSkipped('No paid entries in database');
        }

        // Arrange
        $command = new GenerateExportCommand('csv', 'paid', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testadmin', 'userLevel' => '0']);

        // Act
        $report = $this->service->execute($command, $user);

        // Assert
        $this->assertGreaterThan(0, $report->rowCount());
    }

    public function testExecuteMetadataIsPopulated(): void
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $this->markTestSkipped('No brewing data in database');
        }

        // Arrange
        $command = new GenerateExportCommand('csv', 'all', 'default');
        $user = Identity::fromSession(['loginUsername' => 'testadmin', 'userLevel' => '0']);

        // Act
        $report = $this->service->execute($command, $user);

        // Assert - metadata should be complete
        $this->assertNotNull($report->generatedAt());
        $this->assertInstanceOf(\DateTime::class, $report->generatedAt());
    }
}
