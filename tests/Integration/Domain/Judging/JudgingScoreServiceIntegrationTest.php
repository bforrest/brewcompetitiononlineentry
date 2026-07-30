<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Command\RecordScoreCommand;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Security\Identity;
use DateTime;

class JudgingScoreServiceIntegrationTest extends IntegrationTestCase
{
    private JudgingScoreService $service;
    private JudgingScoreRepository $scoreRepository;
    private Identity $testJudge;
    private TableId $tableId;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $this->scoreRepository = new JudgingScoreRepository($connection, self::$pfx);
        $tableRepository = new JudgingTableRepository($connection, self::$pfx);
        $validationService = new JudgingValidationService();
        $this->service = new JudgingScoreService($this->scoreRepository, $tableRepository, $validationService);

        $this->testJudge = Identity::fromSession([
            'loginUsername' => 'judge@test.local',
            'userLevel' => '2',
        ]);

        $this->tableId = $this->setupTestTable();
    }

    private function setupTestTable(): TableId
    {
        // Create a test table that's ready for judging
        $id = $this->insert('judging_tables', [
            'tableName' => 'Test Judging Table',
            'tableState' => TableState::Active->value,
            'tableLocation' => 1,
            'tableEntryLimit' => 10,
            'tableStateChanged' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return new TableId($id);
    }

    public function testRecordNewScore(): void
    {
        $command = new RecordScoreCommand(
            entryId: 1001,
            tableId: $this->tableId->value(),
            score: 35.5,
            version: 0,
            place: '2',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);

        $score = $this->scoreRepository->getByTableAndEntry(
            $this->tableId,
            new EntryId(1001)
        );

        $this->assertNotNull($score);
        $this->assertEquals(35.5, $score->score());
        $this->assertEquals('2', $score->place());
        $this->assertEquals(1, $score->version());
    }

    public function testUpdateExistingScore(): void
    {
        // Record initial score
        $command1 = new RecordScoreCommand(
            entryId: 1002,
            tableId: $this->tableId->value(),
            score: 30.0,
            version: 0,
            place: '3',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command1, $this->testJudge);

        $score = $this->scoreRepository->getByTableAndEntry(
            $this->tableId,
            new EntryId(1002)
        );

        // Update the score
        $command2 = new RecordScoreCommand(
            entryId: 1002,
            tableId: $this->tableId->value(),
            score: 38.0,
            version: $score->version(),
            place: '1',
            scoreType: 'mini-bos',
            miniBos: 1
        );

        $this->service->recordScore($command2, $this->testJudge);

        $updated = $this->scoreRepository->getByTableAndEntry(
            $this->tableId,
            new EntryId(1002)
        );

        $this->assertEquals(38.0, $updated->score());
        $this->assertEquals('1', $updated->place());
        $this->assertEquals(2, $updated->version());
    }

    public function testGetScore(): void
    {
        $command = new RecordScoreCommand(
            entryId: 1003,
            tableId: $this->tableId->value(),
            score: 42.0,
            version: 0,
            place: '1',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);

        $score = $this->service->getScore(
            $this->tableId,
            new EntryId(1003)
        );

        $this->assertNotNull($score);
        $this->assertEquals(42.0, $score->score());
    }

    public function testListScoresForTable(): void
    {
        $this->service->recordScore(
            new RecordScoreCommand(1004, $this->tableId->value(), 30.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1005, $this->tableId->value(), 35.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1006, $this->tableId->value(), 40.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $scores = $this->service->listScoresForTable($this->tableId);

        $this->assertCount(3, $scores);
    }

    public function testListScoresForEntry(): void
    {
        $entryId = 1007;

        $this->service->recordScore(
            new RecordScoreCommand($entryId, $this->tableId->value(), 32.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $scores = $this->service->listScoresForEntry(new EntryId($entryId));

        $this->assertGreaterThanOrEqual(1, count($scores));
    }

    public function testCountScoresForTable(): void
    {
        $this->service->recordScore(
            new RecordScoreCommand(1008, $this->tableId->value(), 25.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1009, $this->tableId->value(), 28.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $count = $this->service->countScoresForTable($this->tableId);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testValidationFailsForInvalidScore(): void
    {
        $this->expectException(\Bcoem\Domain\Judging\Exception\InvalidScoreException::class);

        $command = new RecordScoreCommand(
            entryId: 1010,
            tableId: $this->tableId->value(),
            score: 60.0, // Invalid: > 50
            version: 0,
            place: null,
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);
    }
}
