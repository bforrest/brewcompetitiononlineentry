<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Kernel\Controller;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingTableService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Kernel\Controller\JudgingController;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use BCOEM\Tests\Integration\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * NOTE on scope: only the two read paths that are provably correct against
 * the real (migrated) schema are exercised here with a real DB.
 * `JudgingTableRepository::insert()` and `::rowToTable()` have a real,
 * separate, pre-existing bug (found while writing this test - not
 * introduced by this migration, not fixed here):
 *
 *  - insert() writes to a `tableJudges` column that does not exist in the
 *    real schema (confirmed against both the live dev DB's
 *    `SHOW CREATE TABLE baseline_judging_tables` and
 *    sql/bcoem_baseline_3.0.X.sql:497-506 plus
 *    db/migrations/20260721160001_add_judging_table_state.php, which
 *    together define the real column set: id, tableName, tableStyles,
 *    tableNumber, tableLocation, tableEntryLimit, tableStewards, tableState,
 *    tableStateChanged). It also never writes the real `tableEntryLimit`
 *    column, so entryLimit is silently dropped even if the column-name bug
 *    were fixed. Confirmed via a standalone mysqli::prepare() repro of the
 *    exact SQL: "Unknown column 'tableJudges' in 'INSERT INTO'".
 *  - JudgingTableService::createTable() can't even reach that SQL: it
 *    builds a placeholder `new TableId(0)` before insert, but TableId's own
 *    constructor throws InvalidArgumentException for any value <= 0 -
 *    confirmed via a standalone repro with no DB involved at all.
 *  - rowToTable() reads `$row['tableState']` with no isset() guard (unlike
 *    its tableEntryLimit/tableStateChanged reads, which are guarded) - a
 *    defensive-programming gap, though NOT currently fatal: the
 *    `tableState` column does exist in the live/migrated schema (added by
 *    the migration above), confirmed by manually inserting a
 *    correctly-shaped row via raw SQL and reading it back successfully
 *    through this repository.
 *
 * Net effect: the create-table path is unconditionally broken (dies before
 * touching the DB), so no test in this DB-backed class ever calls
 * createTable() or otherwise seeds a table row. The two tests below only
 * exercise: (1) the empty-list branch of getTablesView() - a location with
 * zero rows never reaches the broken rowToTable(), and (2) getTableForm()
 * in create mode, which never reads a row at all. Detail-view and
 * scoresheet template/LayoutRenderer correctness are instead covered
 * DB-free in tests/Unit/Kernel/Controller/JudgingHtmlRenderingTest.php,
 * which builds fixture JudgingTable/Flight/Score domain objects directly
 * and bypasses the broken repository entirely.
 */
class JudgingControllerTest extends IntegrationTestCase
{
    private JudgingController $controller;
    private JudgingTableService $tableService;
    private Identity $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $tableRepository = new JudgingTableRepository($connection, self::$pfx);
        $scoreRepository = new JudgingScoreRepository($connection, self::$pfx);
        $validation = new JudgingValidationService();

        $this->tableService = new JudgingTableService($tableRepository, $validation);
        $scoreService = new JudgingScoreService($scoreRepository, $tableRepository, $validation);

        $this->controller = new JudgingController($this->tableService, $scoreService, new LayoutRenderer());

        $this->admin = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
    }

    public function test_get_tables_view_renders_empty_state_with_real_bootstrap_classes(): void
    {
        // Deliberately no fixture table is created: JudgingTableService::
        // createTable() is unconditionally broken today (see class docblock).
        // Location 1 has no rows, so listTablesByLocation() returns []
        // without ever touching the broken rowToTable() - this exercises
        // admin-table-list.php's explicit empty-state branch.
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/judging/tables?location=1')
            ->withAttribute('identity', $this->admin);
        $response = $this->controller->getTablesView($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('No tables found', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringNotContainsString('button-primary', $html);
        $this->assertStringContainsString('class="sidebar', $html);
        $this->assertStringContainsString('<nav', $html);
    }

    public function test_get_table_form_returns_styled_html(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/judging/tables/create?location=1')
            ->withAttribute('identity', $this->admin);
        $response = $this->controller->getTableForm($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('Create New Table', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('class="sidebar', $html);
    }
}
