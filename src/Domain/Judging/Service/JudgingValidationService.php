<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Service;

use Bcoem\Domain\Judging\Command\RecordScoreCommand;
use Bcoem\Domain\Judging\Exception\InvalidScoreException;
use Bcoem\Domain\Judging\Exception\TableAlreadyLockedException;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\ValueObject\TableState;

/**
 * JudgingValidationService validates business rules for judging operations.
 *
 * Responsibilities:
 * - Score validation (range, type, entry exists, etc.)
 * - Table state checks (can we score at this table?)
 * - Business constraint checks (limits, windows)
 */
final class JudgingValidationService
{
    /**
     * Validate that a score can be recorded at a table.
     *
     * @throws InvalidScoreException if validation fails
     * @throws TableAlreadyLockedException if table is locked
     */
    public function validateScoreForTable(RecordScoreCommand $command, JudgingTable $table): void
    {
        if ($table->isLocked()) {
            throw new TableAlreadyLockedException(
                sprintf('Table %d is locked; no further scoring allowed', $table->id()->value())
            );
        }

        if (!$table->isReadyForJudging()) {
            throw new InvalidScoreException(
                sprintf('Table %d is not ready for scoring (state: %s)', $table->id()->value(), $table->state()->label())
            );
        }
    }

    /**
     * Validate score range and type.
     *
     * @throws InvalidScoreException
     */
    public function validateScoreRange(float $score): void
    {
        if ($score < 0 || $score > 50) {
            throw new InvalidScoreException(sprintf('Score must be between 0 and 50, got %.2f', $score));
        }
    }

    /**
     * Validate that place is valid for score range.
     *
     * @throws InvalidScoreException
     */
    public function validatePlace(?string $place, float $score): void
    {
        if ($place === null) {
            return;
        }

        if (!ctype_digit($place)) {
            throw new InvalidScoreException(sprintf('Place must be numeric or empty, got: %s', $place));
        }

        $placeInt = (int) $place;
        if ($placeInt < 1 || $placeInt > 999) {
            throw new InvalidScoreException(sprintf('Place must be between 1 and 999, got: %d', $placeInt));
        }
    }

    /**
     * Validate score type.
     *
     * @throws InvalidScoreException
     */
    public function validateScoreType(string $scoreType): void
    {
        $validTypes = ['regular', 'mini-bos', 'bos'];
        if (!in_array($scoreType, $validTypes, true)) {
            throw new InvalidScoreException(
                sprintf('Invalid score type "%s"; must be one of: %s', $scoreType, implode(', ', $validTypes))
            );
        }
    }

    /**
     * Validate that table state allows editing (admin operations).
     *
     * @throws InvalidScoreException
     */
    public function validateTableIsEditable(JudgingTable $table): void
    {
        if (!$table->isEditable()) {
            throw new InvalidScoreException(
                sprintf('Table %d is not editable in %s state', $table->id()->value(), $table->state()->label())
            );
        }
    }
}
