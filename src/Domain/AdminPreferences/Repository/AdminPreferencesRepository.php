<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Domain\AdminPreferences\ValueObject\PreferencesId;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;
use DateTime;

/**
 * AdminPreferencesRepository handles persistence of AdminPreferences aggregate.
 *
 * Preferences are singleton (id = 1). All queries use prepared statements.
 */
class AdminPreferencesRepository
{
    private readonly string $table;
    private readonly string $eventsTable;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->table = $tablePrefix . 'admin_preferences';
        $this->eventsTable = $tablePrefix . 'admin_preferences_events';
    }

    /**
     * Get the singleton AdminPreferences.
     *
     * Creates default if missing.
     */
    public function getById(int $id): AdminPreferences
    {
        if ($id !== 1) {
            throw new \InvalidArgumentException('AdminPreferences is singleton: id must be 1');
        }

        $sql = sprintf('SELECT * FROM %s WHERE id = 1', $this->table);
        $row = $this->connection->selectOne($sql, []);

        if (!$row) {
            return $this->createDefaults();
        }

        return $this->rowToPreferences($row);
    }

    /**
     * Update the singleton AdminPreferences.
     */
    public function save(AdminPreferences $preferences): void
    {
        $sql = sprintf(
            'UPDATE %s SET
                competitionState = ?,
                styleSet = ?,
                allowedStyleIds = ?,
                customStyleExceptions = ?,
                globalEntryLimit = ?,
                perStyleLimits = ?,
                perTableLimit = ?,
                subCategoryLimits = ?,
                isQueued = ?,
                maxFlightEntries = ?,
                maxBosPerStyle = ?,
                maxRounds = ?,
                changedAt = ?,
                changedBy = ?
            WHERE id = 1',
            $this->table
        );

        $this->connection->execute($sql, [
            $preferences->competitionState()->value,
            $preferences->styleSetConfig()->styleSet->value,
            json_encode($preferences->styleSetConfig()->allowedStyleIds),
            json_encode($preferences->styleSetConfig()->customExceptions),
            $preferences->entryConstraints()->globalEntryLimit,
            json_encode($preferences->entryConstraints()->perStyleLimits),
            json_encode($preferences->entryConstraints()->perTableLimit ? [$preferences->entryConstraints()->perTableLimit] : []),
            json_encode($preferences->entryConstraints()->subCategoryLimits),
            $preferences->judgingConfig()->isQueued ? 1 : 0,
            $preferences->judgingConfig()->maxFlightEntries,
            $preferences->judgingConfig()->maxBosPerStyle,
            $preferences->judgingConfig()->maxRounds,
            (new DateTime())->format('Y-m-d H:i:s'),
            0,
        ]);
    }

    /**
     * Record a preference change event.
     */
    public function recordEvent(
        string $action,
        ?array $beforeJson,
        AdminPreferences $after
    ): void {
        $sql = sprintf(
            'INSERT INTO %s (action, beforeJson, afterJson, changedAt) VALUES (?, ?, ?, ?)',
            $this->eventsTable
        );

        $this->connection->execute($sql, [
            $action,
            $beforeJson ? json_encode($beforeJson) : null,
            json_encode($this->preferencesToArray($after)),
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Convert database row to AdminPreferences aggregate.
     *
     * @param array<string, mixed> $row
     */
    private function rowToPreferences(array $row): AdminPreferences
    {
        $styleSetConfig = new StyleSetConfiguration(
            styleSet: StyleSet::from((string) $row['styleSet']),
            allowedStyleIds: json_decode((string) $row['allowedStyleIds'], true) ?? [],
            customExceptions: json_decode((string) $row['customStyleExceptions'], true) ?? []
        );

        $perTableLimitArray = json_decode((string) $row['perTableLimit'], true) ?? [];
        $perTableLimit = !empty($perTableLimitArray) ? (int) $perTableLimitArray[0] : null;

        $entryConstraints = new EntryConstraints(
            globalEntryLimit: (int) $row['globalEntryLimit'],
            perStyleLimits: json_decode((string) $row['perStyleLimits'], true) ?? [],
            perTableLimit: $perTableLimit,
            subCategoryLimits: json_decode((string) $row['subCategoryLimits'], true) ?? []
        );

        $judgingConfig = new JudgingConfiguration(
            isQueued: (bool) $row['isQueued'],
            maxFlightEntries: (int) $row['maxFlightEntries'],
            maxBosPerStyle: (int) $row['maxBosPerStyle'],
            maxRounds: (int) $row['maxRounds']
        );

        return new AdminPreferences(
            id: new PreferencesId(1),
            styleSetConfig: $styleSetConfig,
            entryConstraints: $entryConstraints,
            judgingConfig: $judgingConfig,
            competitionState: CompetitionState::from((string) $row['competitionState']),
            stateChangedAt: new DateTime($row['changedAt'] ?? 'now')
        );
    }

    /**
     * Create default preferences.
     */
    private function createDefaults(): AdminPreferences
    {
        $defaults = new AdminPreferences(
            id: new PreferencesId(1),
            styleSetConfig: new StyleSetConfiguration(
                styleSet: StyleSet::BJCP2025,
                allowedStyleIds: [],
                customExceptions: []
            ),
            entryConstraints: new EntryConstraints(
                globalEntryLimit: 50,
                perStyleLimits: [],
                perTableLimit: null,
                subCategoryLimits: []
            ),
            judgingConfig: new JudgingConfiguration(
                isQueued: false,
                maxFlightEntries: 6,
                maxBosPerStyle: 3,
                maxRounds: 2
            ),
            competitionState: CompetitionState::Planning,
            stateChangedAt: new DateTime()
        );

        $sql = sprintf(
            'INSERT INTO %s (id, competitionState, styleSet, allowedStyleIds, customStyleExceptions, globalEntryLimit, perStyleLimits, perTableLimit, subCategoryLimits, isQueued, maxFlightEntries, maxBosPerStyle, maxRounds, changedAt, changedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $this->table
        );

        $this->connection->execute($sql, [
            1,
            CompetitionState::Planning->value,
            StyleSet::BJCP2025->value,
            json_encode([]),
            json_encode([]),
            50,
            json_encode([]),
            json_encode([]),
            json_encode([]),
            0,
            6,
            3,
            2,
            (new DateTime())->format('Y-m-d H:i:s'),
            0,
        ]);

        return $defaults;
    }

    /**
     * Convert AdminPreferences to array for JSON storage.
     *
     * @return array<string, mixed>
     */
    private function preferencesToArray(AdminPreferences $prefs): array
    {
        return [
            'id' => 1,
            'state' => $prefs->state()->value,
            'styleSet' => $prefs->styleSetConfig()->styleSet->value,
            'allowedStyleIds' => $prefs->styleSetConfig()->allowedStyleIds,
            'customExceptions' => $prefs->styleSetConfig()->customExceptions,
            'globalEntryLimit' => $prefs->entryConstraints()->globalEntryLimit,
            'perStyleLimits' => $prefs->entryConstraints()->perStyleLimits,
            'perTableLimit' => $prefs->entryConstraints()->perTableLimit,
            'subCategoryLimits' => $prefs->entryConstraints()->subCategoryLimits,
            'isQueued' => $prefs->judgingConfig()->isQueued,
            'maxFlightEntries' => $prefs->judgingConfig()->maxFlightEntries,
            'maxBosPerStyle' => $prefs->judgingConfig()->maxBosPerStyle,
            'maxRounds' => $prefs->judgingConfig()->maxRounds,
            'changedAt' => $prefs->changedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
