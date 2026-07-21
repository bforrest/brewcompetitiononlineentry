<?php
declare(strict_types=1);

namespace Tests\Integration\Domain\Judging;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Command\RecordScoreCommand;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Kernel\Identity;
use Bcoem\Kernel\Security\User;
use Bcoem\Kernel\Security\Role;
use DateTime;
use PHPUnit\Framework\TestCase;

class JudgingScoreServiceIntegrationTest extends TestCase
{
    private Connection $connection;
    private JudgingScoreService $service;
    private JudgingScoreRepository $scoreRepository;
    private JudgingTableRepository $tableRepository;
    private string $tablePrefix = 'test_';
    private Identity $testJudge;

    protected function setUp(): void
    {
        if (!getenv('DB_TEST_HOST')) {
            $this->markTestSkipped('No test database configured');
        }

        $mysqli = new \mysqli(
            getenv('DB_TEST_HOST') ?: 'localhost',
            getenv('DB_TEST_USER') ?: 'root',
            getenv('DB_TEST_PASSWORD') ?: '',
            getenv('DB_TEST_NAME') ?: 'bcoem_test'
        );

        if ($mysqli->connect_error) {
            $this->markTestSkipped('Could not connect to test database');
        }

        $this->connection = new Connection($mysqli);
        $this->scoreRepository = new JudgingScoreRepository($this->connection, $this->tablePrefix);
        $this->tableRepository = new JudgingTableRepository($this->connection, $this->tablePrefix);
        $validationService = new JudgingValidationService();
        $this->service = new JudgingScoreService($this->scoreRepository, $this->tableRepository, $validationService);

        $user = new User(id: 100, email: 'judge@test.local', name: 'Test Judge');
        $this->testJudge = new Identity($user, [Role::Judge]);

        $this->cleanupTestTables();
        $this->setupTestTable();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTables();
    }

    private function cleanupTestTables(): void
    {
        try {
            $this->connection->execute("DELETE FROM {$this->tablePrefix}judging_scores", []);
            $this->connection->execute("DELETE FROM {$this->tablePrefix}judging_tables", []);
        } catch (\Throwable) {
            // Tables may not exist
        }
    }

    private function setupTestTable(): void
    {
        // Create a test table that's ready for judging
        $sql = sprintf(
            'INSERT INTO %s (name, tableState, location, entryLimit, tableStateChanged) VALUES (?, ?, ?, ?, ?)',
            $this->tablePrefix . 'judging_tables'
        );

        $this->connection->execute($sql, [
            'Test Judging Table',
            TableState::Active->value,
            1,
            10,
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function testRecordNewScore(): void
    {
        $command = new RecordScoreCommand(
            entryId: 1001,
            tableId: 1,
            score: 35.5,
            version: 0,
            place: '2',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);

        $score = $this->scoreRepository->getByTableAndEntry(
            new TableId(1),
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
            tableId: 1,
            score: 30.0,
            version: 0,
            place: '3',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command1, $this->testJudge);

        $score = $this->scoreRepository->getByTableAndEntry(
            new TableId(1),
            new EntryId(1002)
        );

        // Update the score
        $command2 = new RecordScoreCommand(
            entryId: 1002,
            tableId: 1,
            score: 38.0,
            version: $score->version(),
            place: '1',
            scoreType: 'mini-bos',
            miniBos: 1
        );

        $this->service->recordScore($command2, $this->testJudge);

        $updated = $this->scoreRepository->getByTableAndEntry(
            new TableId(1),
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
            tableId: 1,
            score: 42.0,
            version: 0,
            place: '1',
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);

        $score = $this->service->getScore(
            new TableId(1),
            new EntryId(1003)
        );

        $this->assertNotNull($score);
        $this->assertEquals(42.0, $score->score());
    }

    public function testListScoresForTable(): void
    {
        $this->service->recordScore(
            new RecordScoreCommand(1004, 1, 30.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1005, 1, 35.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1006, 1, 40.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $scores = $this->service->listScoresForTable(new TableId(1));

        $this->assertCount(3, $scores);
    }

    public function testListScoresForEntry(): void
    {
        $entryId = 1007;

        // Record scores for the same entry at multiple tables
        // (would need multiple test tables in real scenario)
        $this->service->recordScore(
            new RecordScoreCommand($entryId, 1, 32.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $scores = $this->service->listScoresForEntry(new EntryId($entryId));

        $this->assertGreaterThanOrEqual(1, count($scores));
    }

    public function testCountScoresForTable(): void
    {
        $this->service->recordScore(
            new RecordScoreCommand(1008, 1, 25.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $this->service->recordScore(
            new RecordScoreCommand(1009, 1, 28.0, 0, null, 'regular', 0),
            $this->testJudge
        );

        $count = $this->service->countScoresForTable(new TableId(1));

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testValidationFailsForInvalidScore(): void
    {
        $this->expectException(\Bcoem\Domain\Judging\Exception\InvalidScoreException::class);

        $command = new RecordScoreCommand(
            entryId: 1010,
            tableId: 1,
            score: 60.0, // Invalid: > 50
            version: 0,
            place: null,
            scoreType: 'regular',
            miniBos: 0
        );

        $this->service->recordScore($command, $this->testJudge);
    }
}
