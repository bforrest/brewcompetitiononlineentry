<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Exception\InvalidArchiveException;
use Bcoem\Domain\Export\ValueObject\ExportFilter;

class ParticipantExportRepository
{
    private readonly string $brewerTable;
    private readonly string $staffTable;
    private readonly string $tablePrefix;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->tablePrefix = $tablePrefix;
        $this->brewerTable = $tablePrefix . 'brewer';
        $this->staffTable = $tablePrefix . 'staff';
    }

    /**
     * Get participants filtered by role/availability.
     *
     * Replaces sprintf logic from output_participants_export.db.php with parameterized queries.
     */
    public function getParticipantsByFilter(
        ExportFilter $filter,
        ?int $competitionId = null
    ): array {
        $params = [];

        $sql = match ($filter) {
            ExportFilter::JUDGES => $this->getJudgesQuery($competitionId, $params),
            ExportFilter::STEWARDS => $this->getStewardsQuery($competitionId, $params),
            ExportFilter::STAFF => $this->getStaffQuery($competitionId, $params),
            ExportFilter::AVAIL_JUDGES => $this->getAvailableJudgesQuery($params),
            ExportFilter::AVAIL_STEWARDS => $this->getAvailableStewardsQuery($params),
            default => $this->getAllBrewersQuery($params),
        };

        return $this->connection->select($sql, $params);
    }

    private function getJudgesQuery(?int $competitionId, array &$params): string
    {
        $sql = "SELECT a.brewerEmail, a.brewerFirstName, a.brewerLastName, a.brewerJudgeLocation,
                a.brewerStewardLocation, a.uid, a.brewerJudgeRank, a.brewerJudgeID, a.brewerJudgeLikes,
                a.brewerJudgeDislikes, a.brewerJudgeMead, a.brewerJudgeCider, b.uid
                FROM " . $this->brewerTable . " a, " . $this->staffTable . " b
                WHERE b.staff_judge = ? AND a.uid = b.uid";

        $params[] = 1;

        if ($competitionId) {
            $sql .= " AND b.comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY a.brewerLastName, a.brewerFirstName ASC";

        return $sql;
    }

    private function getStewardsQuery(?int $competitionId, array &$params): string
    {
        $sql = "SELECT a.brewerEmail, a.brewerFirstName, a.brewerLastName, a.uid, a.brewerJudgeRank,
                a.brewerJudgeID, a.brewerJudgeLocation, a.brewerStewardLocation, a.brewerJudgeLikes,
                a.brewerJudgeDislikes, b.uid
                FROM " . $this->brewerTable . " a, " . $this->staffTable . " b
                WHERE b.staff_steward = ? AND a.uid = b.uid";

        $params[] = 1;

        if ($competitionId) {
            $sql .= " AND b.comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY a.brewerLastName, a.brewerFirstName ASC";

        return $sql;
    }

    private function getStaffQuery(?int $competitionId, array &$params): string
    {
        $sql = "SELECT a.brewerEmail, a.brewerFirstName, a.brewerLastName, a.uid, a.brewerJudgeRank,
                a.brewerJudgeID, a.brewerJudgeLocation, a.brewerStewardLocation, a.brewerStaff, b.uid, b.staff_staff
                FROM " . $this->brewerTable . " a, " . $this->staffTable . " b
                WHERE a.brewerStaff = ? AND a.uid = b.uid";

        $params[] = 'Y';

        if ($competitionId) {
            $sql .= " AND b.comp_id = ?";
            $params[] = $competitionId;
        }

        $sql .= " ORDER BY a.brewerLastName, a.brewerFirstName ASC";

        return $sql;
    }

    private function getAvailableJudgesQuery(array &$params): string
    {
        $sql = "SELECT uid, brewerFirstName, brewerLastName, brewerEmail, brewerJudge, brewerJudgeRank,
                brewerJudgeID, brewerSteward, brewerJudgeLocation, brewerStewardLocation, brewerJudgeLikes,
                brewerJudgeDislikes, brewerJudgeMead, brewerJudgeCider
                FROM " . $this->brewerTable . "
                WHERE brewerJudge = ?";

        $params[] = 'Y';

        $sql .= " ORDER BY brewerLastName, brewerFirstName ASC";

        return $sql;
    }

    private function getAvailableStewardsQuery(array &$params): string
    {
        $sql = "SELECT uid, brewerFirstName, brewerLastName, brewerEmail, brewerJudge, brewerJudgeRank,
                brewerJudgeID, brewerSteward, brewerJudgeLocation, brewerStewardLocation, brewerJudgeLikes,
                brewerJudgeDislikes
                FROM " . $this->brewerTable . "
                WHERE brewerSteward = ?";

        $params[] = 'Y';

        $sql .= " ORDER BY brewerLastName, brewerFirstName ASC";

        return $sql;
    }

    private function getAllBrewersQuery(array &$params): string
    {
        $sql = "SELECT uid, brewerFirstName, brewerLastName, brewerEmail, brewerAddress, brewerCity,
                brewerState, brewerZip, brewerCountry, brewerPhone1, brewerClubs, brewerJudge,
                brewerJudgeRank, brewerJudgeID, brewerJudgeMead, brewerJudgeCider, brewerSteward,
                brewerJudgeLocation, brewerStewardLocation, brewerBreweryName, brewerBreweryInfo
                FROM " . $this->brewerTable . "
                ORDER BY brewerLastName ASC";

        return $sql;
    }
}
