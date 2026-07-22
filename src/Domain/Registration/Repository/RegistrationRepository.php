<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\ValueObject\Email;
use Bcoem\Domain\Registration\ValueObject\RegistrantId;

/**
 * Repository for the three tables entrant registration writes:
 * users, brewer, staff. All access is prepared-statement only via Connection.
 */
class RegistrationRepository
{
    private string $tablePrefix;

    public function __construct(private Connection $connection)
    {
        $this->tablePrefix = $GLOBALS['prefix'] ?? 'baseline_';
    }

    public function emailExists(Email $email): bool
    {
        $row = $this->connection->selectOne(
            'SELECT id FROM ' . $this->tablePrefix . 'users WHERE user_name = ?',
            [$email->value()]
        );
        return $row !== null;
    }

    public function insertUser(array $columns): RegistrantId
    {
        $sql = 'INSERT INTO ' . $this->tablePrefix . 'users (' . implode(', ', array_keys($columns)) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->connection->execute($sql, array_values($columns));
        return RegistrantId::from((int) $this->connection->lastInsertId());
    }

    public function insertBrewerProfile(array $columns): void
    {
        $sql = 'INSERT INTO ' . $this->tablePrefix . 'brewer (' . implode(', ', array_keys($columns)) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->connection->execute($sql, array_values($columns));
    }

    public function staffRowExists(int $uid): bool
    {
        $row = $this->connection->selectOne(
            'SELECT id FROM ' . $this->tablePrefix . 'staff WHERE uid = ?',
            [$uid]
        );
        return $row !== null;
    }

    public function insertStaffRow(array $columns): void
    {
        $sql = 'INSERT INTO ' . $this->tablePrefix . 'staff (' . implode(', ', array_keys($columns)) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->connection->execute($sql, array_values($columns));
    }

    public function updateStaffRow(int $uid, array $columns): void
    {
        $set = implode(', ', array_map(fn ($k) => $k . ' = ?', array_keys($columns)));
        $sql = 'UPDATE ' . $this->tablePrefix . 'staff SET ' . $set . ' WHERE uid = ?';
        $this->connection->execute($sql, [...array_values($columns), $uid]);
    }

    /**
     * Prepared-statement equivalent of legacy's judging_location_info($id)[5]
     * (lib/common.lib.php:3773 - a raw mysqli_query this repository must not
     * call directly per phpstan.neon's "no mysqli_* outside Connection" rule).
     *
     * judgingLocType is a tinyint(2) column; mysqli's native driver returns
     * it as PHP int, so it's cast to string here to satisfy this method's
     * ?string contract (callers compare it as a string type code).
     */
    public function judgingLocationType(int $locationId): ?string
    {
        $row = $this->connection->selectOne(
            'SELECT judgingLocType FROM ' . $this->tablePrefix . 'judging_locations WHERE id = ?',
            [$locationId]
        );
        if ($row === null || $row['judgingLocType'] === null) {
            return null;
        }
        return (string) $row['judgingLocType'];
    }

    /** @return array{contestRegistrationOpen: int, contestRegistrationDeadline: int, contestJudgeOpen: int, contestJudgeDeadline: int}|null */
    public function contestDates(): ?array
    {
        return $this->connection->selectOne(
            'SELECT contestRegistrationOpen, contestRegistrationDeadline, contestJudgeOpen, contestJudgeDeadline FROM ' . $this->tablePrefix . 'contest_info WHERE id = 1'
        );
    }

    /**
     * judgingLocType = 2 denotes a non-judging/off-site location (mail-in or
     * distribution point) - not a real judging session - consistently
     * treated as such elsewhere in the legacy codebase. Mirrors
     * includes/constants.inc.php:187's own `WHERE judgingLocType < '2'`
     * filter on this same table, used for the identical "has judging
     * started" determination.
     */
    public function anyJudgingSessionStarted(): bool
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) as count FROM ' . $this->tablePrefix . 'judging_locations WHERE judgingLocType < 2 AND judgingDate IS NOT NULL AND judgingDate <= ?',
            [time()]
        );
        return ((int) ($row['count'] ?? 0)) > 0;
    }
}
