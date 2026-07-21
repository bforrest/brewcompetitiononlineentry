<?php
declare(strict_types=1);

namespace Tests\Integration\Domain\Judging;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Exception\ConcurrentModificationException;
use Bcoem\Domain\Judging\ValueObject\Score;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use DateTime;
use PHPUnit\Framework\TestCase;

class JudgingScoreRepositoryIntegrationTest extends TestCase
{
    private Connection $connection;
    private JudgingScoreRepository $repository;
    private string $tablePrefix = 'test_';

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
        $this->repository = new JudgingScoreRepository($this->connection, $this->tablePrefix);

        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTables();
    }

    private function cleanupTestTables(): void
    {
        try {
            $this->connection->execute("DELETE FROM {$this->tablePrefix}judging_scores", []);
        } catch (\Throwable) {
            // Table may not exist
        }
    }

    public function testInsertAndGetById(): void
    {
        $score = new Score(
            id: 0,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: 35.5,
            place: '1',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );

        $insertedId = $this->repository->insert($score);

        $this->assertGreaterThan(0, $insertedId);

        $retrieved = $this->repository->getById($insertedId);

        $this->assertNotNull($retrieved);
        $this->assertEquals(100, $retrieved->entryId()->value());
        $this->assertEquals(35.5, $retrieved->score());
        $this->assertEquals('1', $retrieved->place());
        $this->assertEquals(1, $retrieved->version());
    }

    public function testGetByTableAndEntry(): void
    {
        $tableId = new TableId(2);
        $entryId = new EntryId(200);

        $score = new Score(
            id: 0,
            entryId: $entryId,
            brewerId: 60,
            tableId: $tableId,
            score: 40.0,
            place: '2',
            scoreType: 'mini-bos',
            miniBos: 1,
            version: 1
        );

        $insertedId = $this->repository->insert($score);

        $retrieved = $this->repository->getByTableAndEntry($tableId, $entryId);

        $this->assertNotNull($retrieved);
        $this->assertEquals($insertedId, $retrieved->id());
        $this->assertEquals(40.0, $retrieved->score());
    }

    public function testListByTable(): void
    {
        $tableId = new TableId(3);

        $this->insertScore($tableId, new EntryId(300), 30.0, '3');
        $this->insertScore($tableId, new EntryId(301), 35.0, '2');
        $this->insertScore($tableId, new EntryId(302), 40.0, '1');

        $scores = $this->repository->listByTable($tableId);

        $this->assertCount(3, $scores);
        $this->assertEquals(30.0, $scores[0]->score());
    }

    public function testListByEntry(): void
    {
        $entryId = new EntryId(400);

        $this->insertScore(new TableId(4), $entryId, 32.0, '1');
        $this->insertScore(new TableId(5), $entryId, 38.0, '2');

        $scores = $this->repository->listByEntry($entryId);

        $this->assertCount(2, $scores);
    }

    public function testUpdateWithVersionCheck(): void
    {
        $score = new Score(
            id: 0,
            entryId: new EntryId(500),
            brewerId: 70,
            tableId: new TableId(6),
            score: 25.0,
            place: '4',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );

        $insertedId = $this->repository->insert($score);

        $retrieved = $this->repository->getById($insertedId);

        $updated = new Score(
            id: $insertedId,
            entryId: new EntryId(500),
            brewerId: 70,
            tableId: new TableId(6),
            score: 28.0,
            place: '3',
            scoreType: 'regular',
            miniBos: 0,
            version: $retrieved->version()
        );

        $this->repository->updateWithVersionCheck($updated);

        $refreshed = $this->repository->getById($insertedId);

        $this->assertEquals(28.0, $refreshed->score());
        $this->assertEquals('3', $refreshed->place());
        $this->assertEquals(2, $refreshed->version()); // Version incremented
    }

    public function testUpdateWithVersionCheckThrowsOnMismatch(): void
    {
        $score = new Score(
            id: 0,
            entryId: new EntryId(600),
            brewerId: 80,
            tableId: new TableId(7),
            score: 20.0,
            place: '5',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );

        $insertedId = $this->repository->insert($score);

        // Try to update with wrong version (simulating concurrent modification)
        $scoreWithWrongVersion = new Score(
            id: $insertedId,
            entryId: new EntryId(600),
            brewerId: 80,
            tableId: new TableId(7),
            score: 22.0,
            place: '4',
            scoreType: 'regular',
            miniBos: 0,
            version: 1 // Wrong version
        );

        // Manually increment the version in DB to simulate concurrent update
        $this->connection->execute(
            "UPDATE {$this->tablePrefix}judging_scores SET version = 2 WHERE id = ?",
            [$insertedId]
        );

        $this->expectException(ConcurrentModificationException::class);
        $this->repository->updateWithVersionCheck($scoreWithWrongVersion);
    }

    public function testCountByTable(): void
    {
        $tableId = new TableId(8);

        $this->insertScore($tableId, new EntryId(700), 30.0, '1');
        $this->insertScore($tableId, new EntryId(701), 35.0, '2');

        $count = $this->repository->countByTable($tableId);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testDelete(): void
    {
        $score = new Score(
            id: 0,
            entryId: new EntryId(800),
            brewerId: 90,
            tableId: new TableId(9),
            score: 45.0,
            place: '1',
            scoreType: 'bos',
            miniBos: 0,
            version: 1
        );

        $insertedId = $this->repository->insert($score);

        $this->repository->delete($insertedId);

        $retrieved = $this->repository->getById($insertedId);

        $this->assertNull($retrieved);
    }

    private function insertScore(TableId $tableId, EntryId $entryId, float $score, string $place): void
    {
        $sql = sprintf(
            'INSERT INTO %s (eid, bid, scoreTable, scoreEntry, scorePlace, scoreType, scoreMiniBOS, version, scoreUpdated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $this->tablePrefix . 'judging_scores'
        );

        $this->connection->execute($sql, [
            $entryId->value(),
            90,
            $tableId->value(),
            $score,
            $place,
            'regular',
            0,
            1,
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}
