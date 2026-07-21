<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Database\Connection;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use DateTime;

/**
 * Exercises AdminPreferencesRepository against the real admin_preferences /
 * admin_preferences_events tables (created by the 20260721170003 migration).
 *
 * This is the test that would have caught Phase 3.3 Task 1 targeting the
 * wrong tables (legacy `preferences`/`judging_preferences` instead of
 * `admin_preferences`/`admin_preferences_events`) - the repository's own
 * unit tests all mock the Connection, so they never touch real SQL.
 */
class AdminPreferencesRepositoryIntegrationTest extends IntegrationTestCase
{
    private AdminPreferencesRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $this->repository = new AdminPreferencesRepository($connection, self::$pfx);
    }

    public function test_get_by_id_creates_default_row_when_missing(): void
    {
        $preferences = $this->repository->getById(1);

        $this->assertSame(CompetitionState::Planning, $preferences->competitionState());
        $this->assertSame(50, $preferences->entryConstraints()->globalEntryLimit());

        $rows = $this->select('admin_preferences', 'id = 1');
        $this->assertCount(1, $rows);
    }

    public function test_save_persists_updated_entry_constraints(): void
    {
        $preferences = $this->repository->getById(1);
        $preferences->updateEntryConstraints(new EntryConstraints(globalEntryLimit: 42), new DateTime());

        $this->repository->save($preferences);

        $reloaded = $this->repository->getById(1);
        $this->assertSame(42, $reloaded->entryConstraints()->globalEntryLimit());
    }

    public function test_record_event_inserts_audit_row(): void
    {
        $preferences = $this->repository->getById(1);

        $this->repository->recordEvent('state_changed', ['state' => 'planning'], $preferences);

        $rows = $this->select('admin_preferences_events', "action = 'state_changed'");
        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['afterJson']);
    }
}
