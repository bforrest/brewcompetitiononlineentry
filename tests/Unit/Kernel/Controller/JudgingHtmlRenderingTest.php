<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\Controller;

use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\Score;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that templates/Judging/admin-table-detail.php and
 * templates/Judging/judge-scoresheet.php render correctly (real Bootstrap 3
 * classes, correct chrome) when driven through LayoutRenderer directly.
 *
 * This is deliberately DB-free and does NOT go through JudgingController,
 * JudgingTableService, or JudgingTableRepository - those collaborators are
 * `final` (no mocking seam) AND, as of this session, JudgingTableRepository
 * has a real, separate, pre-existing bug (see JudgingControllerTest's class
 * docblock) that makes the create/read DB path for judging tables
 * non-functional against the live schema. Fixture JudgingTable/Flight/Score
 * domain objects are built directly via their real constructors instead,
 * exactly mirroring the shape of data JudgingController::getTableDetailView()
 * and ::getJudgeScoresheet() pass to these same templates in production
 * (see JudgingController.php's compact('table', 'flights', 'scores', ...)
 * calls). This proves the migrated templates + LayoutRenderer pipeline are
 * correct, independent of the separately-tracked Domain-layer bug.
 */
class JudgingHtmlRenderingTest extends TestCase
{
    private LayoutRenderer $renderer;
    private string $detailTemplate;
    private string $scoresheetTemplate;

    protected function setUp(): void
    {
        unset($_SESSION['prefsTheme']);
        $this->renderer = new LayoutRenderer();
        $this->detailTemplate = __DIR__ . '/../../../../templates/Judging/admin-table-detail.php';
        $this->scoresheetTemplate = __DIR__ . '/../../../../templates/Judging/judge-scoresheet.php';
    }

    private function buildTable(TableState $state): JudgingTable
    {
        $flights = new FlightQueue([
            new Flight(new FlightId(1), new EntryId(101), 1, 1),
            new Flight(new FlightId(2), new EntryId(102), 2, 1),
        ]);

        return new JudgingTable(
            new TableId(1),
            'Detail Table',
            $state,
            $flights,
            new LocationId(1),
            10,
            new DateTime('2026-07-23 12:00:00')
        );
    }

    public function test_admin_table_detail_renders_real_bootstrap_classes_with_sidebar(): void
    {
        $table = $this->buildTable(TableState::Active);
        $flights = $table->flights()->all();
        $scores = [
            new Score(1, new EntryId(101), 5, new TableId(1), 42.5, '1', 'regular', 0, 1),
        ];
        $allowedTransitions = $table->state()->getAllowedTransitions();

        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);

        $html = $this->renderer->admin(
            $identity,
            $table->name(),
            'judging',
            $this->detailTemplate,
            compact('table', 'flights', 'scores', 'allowedTransitions')
        );

        $this->assertStringContainsString('Detail Table', $html);
        $this->assertStringContainsString('label label-', $html);
        $this->assertStringNotContainsString('badge-primary', $html);
        $this->assertStringNotContainsString('button-primary', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('class="sidebar', $html);
        $this->assertStringContainsString('<nav', $html);
    }

    public function test_judge_scoresheet_uses_authenticated_layout_without_sidebar(): void
    {
        $table = $this->buildTable(TableState::Active);
        $flights = $table->flights()->all();
        $scores = [
            101 => new Score(1, new EntryId(101), 5, new TableId(1), 42.5, '1', 'regular', 0, 1),
        ];
        $currentIdentity = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);

        $html = $this->renderer->authenticated(
            $currentIdentity,
            'Judging Scoresheet - ' . $table->name(),
            $this->scoresheetTemplate,
            compact('table', 'flights', 'scores', 'currentIdentity')
        );

        $this->assertStringContainsString('judge@test.local', $html);
        $this->assertStringContainsString('label label-', $html);
        $this->assertStringNotContainsString('badge-primary', $html);
        $this->assertStringNotContainsString('button-primary', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('class="table"', $html);
        $this->assertStringNotContainsString('class="sidebar', $html);
        $this->assertStringContainsString('<nav', $html);
    }
}
