<?php

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\Repository\ParticipantExportRepository;
use Bcoem\Domain\Export\Repository\JudgingExportRepository;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportValidationService;
use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Security\Identity;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class ExportServiceIntegrationTest extends IntegrationTestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);

        $brewingRepo = new BrewingExportRepository($connection);
        $participantRepo = new ParticipantExportRepository($connection);
        $judgingRepo = new JudgingExportRepository($connection);

        $validator = (new ValidatorBuilder())
            ->addLoader(new AttributeLoader())
            ->getValidator();
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
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
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
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
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
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
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
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing WHERE brewPaid = 1");
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
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
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
