<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Service;

use Bcoem\Domain\Judging\Command\RecordScoreCommand;
use Bcoem\Domain\Judging\Exception\ConcurrentModificationException;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\ValueObject\Score;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Kernel\Identity;

/**
 * JudgingScoreService orchestrates score recording with optimistic locking.
 *
 * Responsibilities:
 * - Record scores from judges
 * - Handle concurrent modifications (optimistic locking with retry)
 * - Validate table state before recording
 * - Track score history for audit trail
 */
final class JudgingScoreService
{
    private const MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private readonly JudgingScoreRepository $scoreRepository,
        private readonly JudgingTableRepository $tableRepository,
        private readonly JudgingValidationService $validation
    ) {
    }

    /**
     * Record a judge's score with optimistic locking retry logic.
     *
     * If concurrent modification occurs, retries up to 3 times by re-fetching the current version.
     *
     * @throws ConcurrentModificationException after max retries
     */
    public function recordScore(
        RecordScoreCommand $command,
        Identity $judge
    ): void {
        $tableId = new TableId($command->tableId);
        $entryId = new EntryId($command->entryId);

        // Validate command
        $this->validation->validateScoreRange($command->score);
        $this->validation->validatePlace($command->place, $command->score);
        $this->validation->validateScoreType($command->scoreType);

        // Validate table state
        $table = $this->tableRepository->getById($tableId);
        $this->validation->validateScoreForTable($command, $table);

        // Try to update with retry logic
        $attempt = 0;
        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $existingScore = $this->scoreRepository->getByTableAndEntry($tableId, $entryId);

                if ($existingScore) {
                    // Update existing score
                    $score = new Score(
                        id: $existingScore->id(),
                        entryId: $entryId,
                        brewerId: $existingScore->brewerId(),
                        tableId: $tableId,
                        score: $command->score,
                        place: $command->place,
                        scoreType: $command->scoreType,
                        miniBos: $command->miniBos,
                        version: $existingScore->version()
                    );
                    $this->scoreRepository->updateWithVersionCheck($score);
                } else {
                    // Insert new score
                    $score = new Score(
                        id: 0,
                        entryId: $entryId,
                        brewerId: 0,
                        tableId: $tableId,
                        score: $command->score,
                        place: $command->place,
                        scoreType: $command->scoreType,
                        miniBos: $command->miniBos,
                        version: 1
                    );
                    $this->scoreRepository->insert($score);
                }

                return;
            } catch (ConcurrentModificationException $e) {
                $attempt++;
                if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Get score for an entry at a table.
     *
     * @return Score|null
     */
    public function getScore(TableId $tableId, EntryId $entryId): ?Score
    {
        return $this->scoreRepository->getByTableAndEntry($tableId, $entryId);
    }

    /**
     * List all scores at a table.
     *
     * @return array<int, Score>
     */
    public function listScoresForTable(TableId $tableId): array
    {
        return $this->scoreRepository->listByTable($tableId);
    }

    /**
     * List all scores for an entry across all tables.
     *
     * @return array<int, Score>
     */
    public function listScoresForEntry(EntryId $entryId): array
    {
        return $this->scoreRepository->listByEntry($entryId);
    }

    /**
     * Count scores at a table.
     */
    public function countScoresForTable(TableId $tableId): int
    {
        return $this->scoreRepository->countByTable($tableId);
    }

    /**
     * Delete a score (admin operation).
     */
    public function deleteScore(int $scoreId, Identity $admin): void
    {
        $this->scoreRepository->delete($scoreId);
    }
}
