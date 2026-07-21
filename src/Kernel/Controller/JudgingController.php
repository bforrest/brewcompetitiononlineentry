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
use Bcoem\Kernel\Identity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        private readonly JudgingScoreService $scoreService
    ) {
    }

    public function listTables(Request $request): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $locationId = $request->query->getInt('location');
        $state = $request->query->get('state');

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

            return new JsonResponse(['tables' => $data]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getTableDetail(Request $request, int $id): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
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

            return new JsonResponse([
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
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function recordScore(Request $request): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = json_decode($request->getContent(), true);

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

            return new JsonResponse(['success' => true], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new JsonResponse(['error' => $e->getMessage()], $status);
        }
    }

    public function transitionTableState(Request $request, int $id): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$identity->hasRole('admin')) {
            return new JsonResponse(['error' => 'Forbidden: Admin role required'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $newState = TableState::from($data['state']);

            $this->tableService->transitionTableState(new TableId($id), $newState, $identity);

            return new JsonResponse(['success' => true, 'state' => $newState->value]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new JsonResponse(['error' => $e->getMessage()], $status);
        }
    }

    public function addFlight(Request $request, int $id): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$identity->hasRole('admin')) {
            return new JsonResponse(['error' => 'Forbidden: Admin role required'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $flight = new Flight(
                id: new FlightId((int) ($data['flight_id'] ?? 0)),
                entryId: new EntryId((int) $data['entry_id']),
                flightNumber: (int) $data['flight_number'],
                round: (int) $data['round']
            );

            $this->tableService->addFlight(new TableId($id), $flight, $identity);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new JsonResponse(['error' => $e->getMessage()], $status);
        }
    }

    public function removeFlight(Request $request, int $id, int $flightId): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$identity->hasRole('admin')) {
            return new JsonResponse(['error' => 'Forbidden: Admin role required'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->tableService->removeFlight(new TableId($id), new FlightId($flightId), $identity);
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new JsonResponse(['error' => $e->getMessage()], $status);
        }
    }

    public function getTablesView(Request $request): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $locationId = $request->query->getInt('location');
            $state = $request->query->get('state');
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

            ob_start();
            include __DIR__ . '/../../templates/Judging/admin-table-list.php';
            $html = ob_get_clean();

            return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
        } catch (\Throwable $e) {
            return new Response('Error: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getTableDetailView(Request $request, int $id): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $table = $this->tableService->getTable(new TableId($id));
            $scores = $this->scoreService->listScoresForTable(new TableId($id));
            $flights = $table->flights()->all();
            $allowedTransitions = $table->state()->getAllowedTransitions();

            ob_start();
            include __DIR__ . '/../../templates/Judging/admin-table-detail.php';
            $html = ob_get_clean();

            return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new Response('Error: ' . $e->getMessage(), $status);
        }
    }

    public function getJudgeScoresheet(Request $request, int $id): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $table = $this->tableService->getTable(new TableId($id));
            $flights = $table->flights()->all();
            $scores = $this->scoreService->listScoresForTable(new TableId($id));

            $scoresIndex = [];
            foreach ($scores as $score) {
                $scoresIndex[$score->entryId()->value()] = $score;
            }

            $currentIdentity = $identity;

            ob_start();
            include __DIR__ . '/../../templates/Judging/judge-scoresheet.php';
            $html = ob_get_clean();

            return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new Response('Error: ' . $e->getMessage(), $status);
        }
    }

    public function getTableForm(Request $request, ?int $id = null): Response
    {
        $identity = $request->attributes->get('identity');
        if (!$identity instanceof Identity) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$identity->hasRole('admin')) {
            return new JsonResponse(['error' => 'Forbidden: Admin role required'], Response::HTTP_FORBIDDEN);
        }

        try {
            $locationId = $request->query->getInt('location');
            $isEditMode = $id !== null;
            $table = $isEditMode ? $this->tableService->getTable(new TableId($id)) : null;

            ob_start();
            include __DIR__ . '/../../templates/Judging/table-form.php';
            $html = ob_get_clean();

            return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : Response::HTTP_BAD_REQUEST;
            return new Response('Error: ' . $e->getMessage(), $status);
        }
    }
}
