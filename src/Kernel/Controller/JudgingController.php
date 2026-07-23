<?php
declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Judging\Command\RecordScoreCommand;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingTableService;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;
use Bcoem\Kernel\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JudgingController handles HTTP requests for judging operations.
 *
 * Routes:
 * - GET /judging/tables → list all tables
 * - GET /judging/tables/{id} → table detail + flights + scores
 * - POST /judging/tables → create table (admin)
 * - POST /judging/tables/{id}/flights → add flight (admin)
 * - DELETE /judging/tables/{id}/flights/{flightId} → remove flight (admin)
 * - POST /judging/scores → record score (judge)
 * - GET /judging/tables/{id}/scores → list scores at table (admin)
 * - POST /judging/tables/{id}/state → transition state (admin)
 */
final class JudgingController
{
    public function __construct(
        private readonly JudgingTableService $tableService,
        private readonly JudgingScoreService $scoreService,
        private readonly \Bcoem\Kernel\View\LayoutRenderer $layout
    ) {
    }

    public function listTables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        $queryParams = $request->getQueryParams();
        $locationId = (int) ($queryParams['location'] ?? 0);
        $state = $queryParams['state'] ?? null;

        try {
            if ($state) {
                $tableState = TableState::from($state);
                $tables = $this->tableService->listTablesByLocationAndState(
                    new LocationId($locationId),
                    $tableState
                );
            } else {
                $tables = $this->tableService->listTablesByLocation(new LocationId($locationId));
            }

            $data = array_map(fn($table) => [
                'id' => $table->id()->value(),
                'name' => $table->name(),
                'state' => $table->state()->value,
                'location' => $table->location()->value(),
                'entry_limit' => $table->entryLimit(),
                'flights_count' => $table->flights()->count(),
            ], $tables);

            return ResponseHelper::json($response, ['tables' => $data]);
        } catch (\Throwable $e) {
            return ResponseHelper::json($response, ['error' => $e->getMessage()], 400);
        }
    }

    public function getTableDetail(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $id = (int) $id;
            $table = $this->tableService->getTable(new TableId($id));
            $scores = $this->scoreService->listScoresForTable(new TableId($id));

            $flightsData = array_map(fn($flight) => [
                'id' => $flight->id()->value(),
                'entry_id' => $flight->entryId()->value(),
                'flight_number' => $flight->flightNumber(),
                'round' => $flight->round(),
            ], $table->flights()->all());

            $scoresData = array_map(fn($score) => [
                'id' => $score->id(),
                'entry_id' => $score->entryId()->value(),
                'score' => $score->score(),
                'place' => $score->place(),
                'version' => $score->version(),
            ], $scores);

            return ResponseHelper::json($response, [
                'table' => [
                    'id' => $table->id()->value(),
                    'name' => $table->name(),
                    'state' => $table->state()->value,
                    'location' => $table->location()->value(),
                    'entry_limit' => $table->entryLimit(),
                ],
                'flights' => $flightsData,
                'scores' => $scoresData,
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::json($response, ['error' => $e->getMessage()], 400);
        }
    }

    public function recordScore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $data = (array) json_decode($request->getBody()->getContents(), true);

            $command = new RecordScoreCommand(
                entryId: (int) $data['entry_id'],
                tableId: (int) $data['table_id'],
                score: (float) $data['score'],
                version: (int) $data['version'],
                place: $data['place'] ?? null,
                scoreType: $data['score_type'] ?? 'regular',
                miniBos: (int) ($data['mini_bos'] ?? 0)
            );

            $this->scoreService->recordScore($command, $identity);

            return ResponseHelper::json($response, ['success' => true]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::json($response, ['error' => $e->getMessage()], $status);
        }
    }

    public function transitionTableState(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        if (!$identity->role->satisfies(Role::Admin)) {
            return ResponseHelper::json($response, ['error' => 'Forbidden: Admin role required'], 403);
        }

        try {
            $id = (int) $id;
            $data = (array) json_decode($request->getBody()->getContents(), true);
            $newState = TableState::from($data['state']);

            $this->tableService->transitionTableState(new TableId($id), $newState, $identity);

            return ResponseHelper::json($response, ['success' => true, 'state' => $newState->value]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::json($response, ['error' => $e->getMessage()], $status);
        }
    }

    public function addFlight(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        if (!$identity->role->satisfies(Role::Admin)) {
            return ResponseHelper::json($response, ['error' => 'Forbidden: Admin role required'], 403);
        }

        try {
            $id = (int) $id;
            $data = (array) json_decode($request->getBody()->getContents(), true);

            $flight = new Flight(
                id: new FlightId((int) ($data['flight_id'] ?? 0)),
                entryId: new EntryId((int) $data['entry_id']),
                flightNumber: (int) $data['flight_number'],
                round: (int) $data['round']
            );

            $this->tableService->addFlight(new TableId($id), $flight, $identity);

            return ResponseHelper::json($response, ['success' => true]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::json($response, ['error' => $e->getMessage()], $status);
        }
    }

    public function removeFlight(ServerRequestInterface $request, ResponseInterface $response, string $id, string $flightId): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        if (!$identity->role->satisfies(Role::Admin)) {
            return ResponseHelper::json($response, ['error' => 'Forbidden: Admin role required'], 403);
        }

        try {
            $id = (int) $id;
            $flightId = (int) $flightId;
            $this->tableService->removeFlight(new TableId($id), new FlightId($flightId), $identity);
            return ResponseHelper::json($response, ['success' => true]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::json($response, ['error' => $e->getMessage()], $status);
        }
    }

    public function getTablesView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $queryParams = $request->getQueryParams();
            $locationId = (int) ($queryParams['location'] ?? 0);
            $state = $queryParams['state'] ?? null;
            $selectedState = null;

            if ($state) {
                $selectedState = TableState::from($state);
                $tables = $this->tableService->listTablesByLocationAndState(
                    new LocationId($locationId),
                    $selectedState
                );
            } else {
                $tables = $this->tableService->listTablesByLocation(new LocationId($locationId));
            }

            $locationName = "Location #$locationId";
            $states = TableState::cases();
            // Real, previously-latent bug: admin-table-list.php's "Create New
            // Table" link calls $location->value() (a LocationId), but the
            // original controller never defined $location - only $locationId
            // (an int). Masked entirely by the broken include path (this
            // task's Fix #1), which meant this line never actually executed.
            $location = new LocationId($locationId);

            $html = $this->layout->admin(
                $identity,
                'Judging Tables',
                'judging',
                __DIR__ . '/../../../templates/Judging/admin-table-list.php',
                compact('tables', 'location', 'locationName', 'states', 'selectedState')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), 400);
        }
    }

    public function getTableDetailView(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $id = (int) $id;
            $table = $this->tableService->getTable(new TableId($id));
            $scores = $this->scoreService->listScoresForTable(new TableId($id));
            $flights = $table->flights()->all();
            $allowedTransitions = $table->state()->getAllowedTransitions();

            $html = $this->layout->admin(
                $identity,
                $table->name(),
                'judging',
                __DIR__ . '/../../../templates/Judging/admin-table-detail.php',
                compact('table', 'flights', 'scores', 'allowedTransitions')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }

    public function getJudgeScoresheet(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $id = (int) $id;
            $table = $this->tableService->getTable(new TableId($id));
            $flights = $table->flights()->all();
            $scores = $this->scoreService->listScoresForTable(new TableId($id));

            $scoresIndex = [];
            foreach ($scores as $score) {
                $scoresIndex[$score->entryId()->value()] = $score;
            }
            $scores = $scoresIndex;

            $currentIdentity = $identity;

            $html = $this->layout->authenticated(
                $identity,
                'Judging Scoresheet - ' . $table->name(),
                __DIR__ . '/../../../templates/Judging/judge-scoresheet.php',
                compact('table', 'flights', 'scores', 'currentIdentity')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }

    public function getTableForm(ServerRequestInterface $request, ResponseInterface $response, ?string $id = null): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        if (!$identity->role->satisfies(Role::Admin)) {
            return ResponseHelper::json($response, ['error' => 'Forbidden: Admin role required'], 403);
        }

        try {
            $queryParams = $request->getQueryParams();
            $locationId = (int) ($queryParams['location'] ?? 0);
            $id = $id !== null ? (int) $id : null;
            $isEditMode = $id !== null;
            $table = $isEditMode ? $this->tableService->getTable(new TableId($id)) : null;
            $location = new LocationId($locationId);

            $html = $this->layout->admin(
                $identity,
                $isEditMode ? 'Edit Table' : 'Create New Table',
                'judging',
                __DIR__ . '/../../../templates/Judging/table-form.php',
                compact('table', 'location', 'isEditMode')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }
}
