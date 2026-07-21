<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Exception\InvalidArchiveException;
use Bcoem\Domain\Export\ValueObject\ExportFilter;
use DateTime;

class BrewingExportRepository
{
    private readonly string $brewingTable;
    private readonly string $brewingPrefix;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->brewingPrefix = $tablePrefix;
        $this->brewingTable = $tablePrefix . 'brewing';
    }

    /**
     * Get entries filtered by specified criteria.
     *
     * Replaces sprintf logic from output_entries_export.db.php with parameterized queries.
     *
     * @throws \InvalidArgumentException if filter is invalid
     * @return array<int, array<string, mixed>>
     */
    public function getEntriesByFilter(
        ExportFilter $filter,
        string $view = 'default',
        ?int $competitionId = null,
        ?string $archiveSuffix = null
    ): array {
        $table = $this->brewingTable;
        if ($archiveSuffix) {
            $this->validateArchiveSuffix($archiveSuffix);
            $table = $this->brewingPrefix . 'brewing_' . $archiveSuffix;
        }

        $sql = "SELECT DISTINCT id, brewBrewerFirstName, brewBrewerLastName, brewCategory, brewSubCategory,
                brewName, brewInfo, brewInfoOptional, brewComments, brewMead2, brewMead1, brewMead3,
                brewBrewerID, brewJudgingNumber, brewStyle FROM " . $table . " WHERE 1=1";

        $params = [];

        $match = match ($filter) {
            ExportFilter::PAID => [
                'condition' => match ($view) {
                    'all' => 'brewPaid = ?',
                    'not_received' => 'brewPaid = ? AND (brewReceived <> ? OR brewReceived IS NULL)',
                    default => 'brewPaid = ? AND brewReceived = ?',
                },
                'values' => match ($view) {
                    'all' => [1],
                    'not_received' => [1, 1],
                    default => [1, 1],
                },
            ],
            ExportFilter::NOPAY => [
                'condition' => match ($view) {
                    'all' => '(brewPaid <> ? OR brewPaid IS NULL)',
                    default => '(brewPaid <> ? OR brewPaid IS NULL) AND brewReceived = ?',
                },
                'values' => match ($view) {
                    'all' => [1],
                    default => [1, 1],
                },
            ],
            ExportFilter::REQUIRED => [
                'condition' => '(brewInfo IS NOT NULL OR brewComments IS NOT NULL OR brewInfoOptional IS NOT NULL)',
                'values' => [],
            ],
            default => ['condition' => '', 'values' => []],
        };

        if ($match['condition']) {
            $sql .= " AND " . $match['condition'];
            $params = array_merge($params, $match['values']);
        }

        if ($competitionId) {
            $sql .= " AND comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY id ASC";

        return $this->connection->select($sql, $params);
    }

    /**
     * Get all entries for export.
     * @return array<int, array<string, mixed>>
     */
    public function getAllEntries(?int $competitionId = null, ?string $archiveSuffix = null): array
    {
        $table = $this->brewingTable;
        if ($archiveSuffix) {
            $this->validateArchiveSuffix($archiveSuffix);
            $table = $this->brewingPrefix . 'brewing_' . $archiveSuffix;
        }

        $sql = "SELECT * FROM " . $table . " WHERE 1=1";
        $params = [];

        if ($competitionId) {
            $sql .= " AND comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY id ASC";

        return $this->connection->select($sql, $params);
    }

    /**
     * Get winner data for export.
     *
     * Safely constructs query using archive suffix validation.
     * @return array<int, array<string, mixed>>
     */
    public function getWinnerData(?string $archiveSuffix = null, ?int $competitionId = null): array
    {
        $judgingTablesTable = $this->brewingPrefix . 'judging_tables';
        if ($archiveSuffix) {
            $this->validateArchiveSuffix($archiveSuffix);
            $judgingTablesTable = $this->brewingPrefix . 'judging_tables_' . $archiveSuffix;
        }

        $sql = "SELECT id, tableNumber, tableName FROM " . $judgingTablesTable . " WHERE 1=1";
        $params = [];

        if ($competitionId) {
            $sql .= " AND comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY tableNumber ASC";

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
