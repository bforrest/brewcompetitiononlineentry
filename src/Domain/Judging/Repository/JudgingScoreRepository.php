<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Judging\Exception\ConcurrentModificationException;
use Bcoem\Domain\Judging\ValueObject\Score;
use Bcoem\Domain\Judging\ValueObject\TableId;
use DateTime;

/**
 * JudgingScoreRepository handles score records with optimistic locking.
 *
 * Optimistic locking: when updating a score, we check that the version matches.
 * If another judge updated it, version will be higher, update fails, caller retries.
 *
 * All queries use prepared statements via Connection wrapper.
 */
final class JudgingScoreRepository
{
    private readonly string $table;
    private readonly string $bosTable;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->table = $tablePrefix . 'judging_scores';
        $this->bosTable = $tablePrefix . 'judging_scores_bos';
    }

    /**
     * Get score by ID.
     *
     * @return Score|null
     */
    public function getById(int $scoreId): ?Score
    {
        $sql = sprintf('SELECT * FROM %s WHERE id = ?', $this->table);
        $row = $this->connection->selectOne($sql, [$scoreId]);

        return $row ? $this->rowToScore($row) : null;
    }

    /**
     * Get score for entry at table (unique: one score per entry per table).
     *
     * @return Score|null
     */
    public function getByTableAndEntry(TableId $tableId, EntryId $entryId): ?Score
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE scoreTable = ? AND eid = ?',
            $this->table
        );
        $row = $this->connection->selectOne($sql, [$tableId->value(), $entryId->value()]);

        return $row ? $this->rowToScore($row) : null;
    }

    /**
     * List all scores for a table.
     *
     * @return array<int, Score>
     */
    public function listByTable(TableId $tableId): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE scoreTable = ? ORDER BY eid ASC',
            $this->table
        );
        $rows = $this->connection->select($sql, [$tableId->value()]);

        return array_map(fn($row) => $this->rowToScore($row), $rows);
    }

    /**
     * List all scores for an entry (across all tables).
     *
     * @return array<int, Score>
     */
    public function listByEntry(EntryId $entryId): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE eid = ? ORDER BY scoreTable ASC',
            $this->table
        );
        $rows = $this->connection->select($sql, [$entryId->value()]);

        return array_map(fn($row) => $this->rowToScore($row), $rows);
    }

    /**
     * Insert a new score.
     *
     * @return int Score ID
     */
    public function insert(Score $score): int
    {
        $sql = sprintf(
            'INSERT INTO %s (eid, bid, scoreTable, scoreEntry, scorePlace, scoreType, scoreMiniBOS, version, scoreUpdated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $this->table
        );

        $this->connection->execute($sql, [
            $score->entryId()->value(),
            $score->brewerId(),
            $score->tableId()->value(),
            $score->score(),
            $score->place(),
            $score->scoreType(),
            $score->miniBos(),
            $score->version(),
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $this->connection->lastInsertId();
    }

    /**
     * Update score with optimistic locking.
     *
     * Only succeeds if current version in DB matches expected version.
     * On success, version is incremented in DB.
     *
     * @throws ConcurrentModificationException if version mismatch (another judge changed it)
     */
    public function updateWithVersionCheck(Score $score): void
    {
        $sql = sprintf(
            'UPDATE %s SET scoreEntry = ?, scorePlace = ?, scoreType = ?, scoreMiniBOS = ?, version = version + 1, scoreUpdated = ? WHERE id = ? AND version = ?',
            $this->table
        );

        $affectedRows = $this->connection->execute($sql, [
            $score->score(),
            $score->place(),
            $score->scoreType(),
            $score->miniBos(),
            (new DateTime())->format('Y-m-d H:i:s'),
            $score->id(),
            $score->version(),
        ]);

        if ($affectedRows === 0) {
            throw new ConcurrentModificationException(
                sprintf('Score %d was modified by another judge; please refresh and retry', $score->id())
            );
        }
    }

    /**
     * Delete score.
     */
    public function delete(int $scoreId): void
    {
        $sql = sprintf('DELETE FROM %s WHERE id = ?', $this->table);
        $this->connection->execute($sql, [$scoreId]);
    }

    /**
     * Count scores for a table.
     */
    public function countByTable(TableId $tableId): int
    {
        $sql = sprintf('SELECT COUNT(*) as count FROM %s WHERE scoreTable = ?', $this->table);
        $row = $this->connection->selectOne($sql, [$tableId->value()]);
        return (int) $row['count'];
    }

    /**
     * Convert database row to Score value object.
     *
     * @param array<string, mixed> $row
     */
    private function rowToScore(array $row): Score
    {
        return new Score(
            id: (int) $row['id'],
            entryId: new EntryId((int) $row['eid']),
            brewerId: (int) $row['bid'],
            tableId: new TableId((int) $row['scoreTable']),
            score: (float) $row['scoreEntry'],
            place: $row['scorePlace'],
            scoreType: (string) $row['scoreType'],
            miniBos: (int) $row['scoreMiniBOS'],
            version: (int) $row['version']
        );
    }

    /**
     * Insert BOS (Best-of-Show) score.
     *
     * @return int BOS Score ID
     */
    public function insertBos(Score $score): int
    {
        $sql = sprintf(
            'INSERT INTO %s (eid, bid, scoreEntry, scorePlace, scoreType, version, scoreUpdated) VALUES (?, ?, ?, ?, ?, ?, ?)',
            $this->bosTable
        );

        $this->connection->execute($sql, [
            $score->entryId()->value(),
            $score->brewerId(),
            $score->score(),
            $score->place(),
            $score->scoreType(),
            $score->version(),
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $this->connection->lastInsertId();
    }

    /**
     * Update BOS score with version check.
     *
     * @throws ConcurrentModificationException
     */
    public function updateBosWithVersionCheck(Score $score): void
    {
        $sql = sprintf(
            'UPDATE %s SET scoreEntry = ?, scorePlace = ?, scoreType = ?, version = version + 1, scoreUpdated = ? WHERE id = ? AND version = ?',
            $this->bosTable
        );

        $affectedRows = $this->connection->execute($sql, [
            $score->score(),
            $score->place(),
            $score->scoreType(),
            (new DateTime())->format('Y-m-d H:i:s'),
            $score->id(),
            $score->version(),
        ]);

        if ($affectedRows === 0) {
            throw new ConcurrentModificationException('BOS score was modified by another judge');
        }
    }

    /**
     * Get BOS score by entry.
     *
     * @return Score|null
     */
    public function getBosByEntry(EntryId $entryId): ?Score
    {
        $sql = sprintf('SELECT * FROM %s WHERE eid = ?', $this->bosTable);
        $row = $this->connection->selectOne($sql, [$entryId->value()]);

        if (!$row) {
            return null;
        }

        return new Score(
            id: (int) $row['id'],
            entryId: new EntryId((int) $row['eid']),
            brewerId: (int) $row['bid'],
            tableId: new TableId(0),
            score: (float) $row['scoreEntry'],
            place: $row['scorePlace'],
            scoreType: (string) $row['scoreType'],
            miniBos: 0,
            version: (int) $row['version']
        );
    }
}
