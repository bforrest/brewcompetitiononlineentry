<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Entry\Entry;
use Bcoem\Domain\Entry\Exception\EntryNotFoundException;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\BrewerInfo;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;

/**
 * Repository for Entry aggregates. All database access is prepared-statement only.
 * Responsible for: SELECT queries, INSERT/UPDATE/DELETE, hydrating Entry from DB rows.
 *
 * Table prefix is handled by the Table prefix adapter in phinx.php and legacy globals.
 * This repository receives only logical table names (e.g., 'brewing', 'brewer').
 */
class EntryRepository
{
    private string $tablePrefix;

    public function __construct(
        private Connection $connection,
    ) {
        // Prefix is set in site/config.php and available globally
        $this->tablePrefix = $GLOBALS['prefix'] ?? 'baseline_';
    }

    /**
     * Get entry by ID.
     *
     * @throws EntryNotFoundException
     */
    public function getById(EntryId $id): Entry
    {
        $row = $this->connection->selectOne(
            'SELECT b.*, br.uid, br.brewerFirstName as first_name, br.brewerLastName as last_name, br.brewerEmail as email
             FROM ' . $this->tablePrefix . 'brewing b
             LEFT JOIN ' . $this->tablePrefix . 'brewer br ON b.brewBrewerId = br.uid
             WHERE b.id = ?',
            [$id->value()]
        );

        if (!$row) {
            throw new EntryNotFoundException('Entry #' . $id . ' not found');
        }

        return $this->rowToEntry($row);
    }

    /**
     * Get entry by ID and verify ownership (brewer).
     *
     * @throws EntryNotFoundException
     */
    public function getByIdAndBrewerId(EntryId $id, BrewerId $brewerId): Entry
    {
        $entry = $this->getById($id);
        if (!$entry->brewerId()->equals($brewerId)) {
            throw new EntryNotFoundException('Entry #' . $id . ' does not belong to brewer #' . $brewerId);
        }
        return $entry;
    }

    /**
     * List entries by brewer, ordered by creation date descending.
     *
     * @return array<int, Entry>
     */
    public function listByBrewerId(BrewerId $brewerId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->select(
            'SELECT b.*, br.uid, br.brewerFirstName as first_name, br.brewerLastName as last_name, br.brewerEmail as email
             FROM ' . $this->tablePrefix . 'brewing b
             LEFT JOIN ' . $this->tablePrefix . 'brewer br ON b.brewBrewerId = br.uid
             WHERE b.brewBrewerId = ?
             ORDER BY b.id DESC
             LIMIT ? OFFSET ?',
            [$brewerId->value(), $limit, $offset]
        );

        return array_map(fn (array $row) => $this->rowToEntry($row), $rows);
    }

    /**
     * Count entries by brewer.
     */
    public function countByBrewerId(BrewerId $brewerId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) as count FROM ' . $this->tablePrefix . 'brewing WHERE brewBrewerId = ?',
            [$brewerId->value()]
        );
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Count entries by brewer and category.
     */
    public function countByBrewerIdAndStyle(BrewerId $brewerId, StyleNumber $styleNumber): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) as count FROM ' . $this->tablePrefix . 'brewing
             WHERE brewBrewerId = ? AND brewCategorySort = ?',
            [$brewerId->value(), $styleNumber->group()]
        );
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Insert a new entry.
     */
    public function insert(Entry $entry): EntryId
    {
        $row = $this->entryToRow($entry);
        unset($row['id']); // auto-increment; entry doesn't have a real id yet

        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $sql = 'INSERT INTO ' . $this->tablePrefix . 'brewing (' . $columns . ') VALUES (' . $placeholders . ')';

        $this->connection->execute($sql, array_values($row));
        return EntryId::from((int) $this->connection->lastInsertId());
    }

    /**
     * Update an existing entry.
     */
    public function update(Entry $entry): void
    {
        $row = $this->entryToRow($entry);
        $id = $row['id'];
        unset($row['id']); // id cannot be updated

        $set = implode(', ', array_map(fn ($k) => $k . ' = ?', array_keys($row)));
        $sql = 'UPDATE ' . $this->tablePrefix . 'brewing SET ' . $set . ' WHERE id = ?';

        $this->connection->execute($sql, [...array_values($row), $id]);
    }

    /**
     * Delete an entry (soft-delete via a flag, or hard-delete).
     * For now, hard delete; audit log captures before/after.
     */
    public function delete(EntryId $id): void
    {
        $this->connection->execute(
            'DELETE FROM ' . $this->tablePrefix . 'brewing WHERE id = ?',
            [$id->value()]
        );
    }

    /**
     * Hydrate an Entry from a database row.
     */
    private function rowToEntry(array $row): Entry
    {
        $brewerInfo = BrewerInfo::fromDatabaseRow($row);

        return new Entry(
            id: EntryId::from((int) $row['id']),
            brewerId: BrewerId::from((int) $row['brewBrewerID']),
            style: StyleNumber::from($row['brewCategorySort'] ?? '', $row['brewSubCategory'] ?? ''),
            name: $row['brewName'] ?? '',
            brewer: $brewerInfo,
            confirmed: (bool) ($row['brewConfirmed'] ?? false),
            paid: (bool) ($row['brewPaid'] ?? false),
            received: (bool) ($row['brewReceived'] ?? false),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Flatten an Entry to a database row for insert/update.
     */
    private function entryToRow(Entry $entry): array
    {
        $style = $entry->style();
        return [
            'id' => $entry->id()->value(),
            'brewBrewerId' => $entry->brewerId()->value(),
            'brewCategorySort' => $style->group(),
            'brewSubCategory' => $style->num(),
            'brewName' => $entry->name(),
            'brewConfirmed' => (int) $entry->isConfirmed(),
            'brewPaid' => (int) $entry->isPaid(),
            'brewReceived' => (int) $entry->isReceived(),
        ];
    }
}
