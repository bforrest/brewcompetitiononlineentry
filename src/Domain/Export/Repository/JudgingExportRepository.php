<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Exception\InvalidArchiveException;

class JudgingExportRepository
{
    private readonly string $judgingScoresTable;
    private readonly string $tablePrefix;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->tablePrefix = $tablePrefix;
        $this->judgingScoresTable = $tablePrefix . 'judging_scores';
    }

    /**
     * Get judging scores for export.
     *
     * @throws InvalidArchiveException if archive suffix is invalid
     * @return array<int, array<string, mixed>>
     */
    public function getJudgingScores(
        bool $bosOnly = false,
        ?int $competitionId = null,
        ?string $archiveSuffix = null
    ): array {
        $table = $this->judgingScoresTable;
        if ($archiveSuffix) {
            $this->validateArchiveSuffix($archiveSuffix);
            $table = $this->tablePrefix . 'judging_scores_' . $archiveSuffix;
        }

        $sql = "SELECT * FROM " . $table . " WHERE 1=1";
        $params = [];

        if ($bosOnly) {
            $sql .= " AND isBos = ?";
            $params[] = 1;
        }

        if ($competitionId) {
            $sql .= " AND comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY round ASC, flight ASC";

        return $this->connection->select($sql, $params);
    }

    /**
     * Validate archive suffix to prevent injection.
     */
    private function validateArchiveSuffix(string $suffix): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $suffix)) {
            throw new InvalidArchiveException(
                sprintf('Invalid archive suffix: %s', $suffix)
            );
        }
    }
}
