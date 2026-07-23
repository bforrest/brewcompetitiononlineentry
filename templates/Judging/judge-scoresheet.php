<?php
/**
 * Judge: Scoresheet for recording scores
 *
 * Available variables:
 * - $table: JudgingTable
 * - $flights: array<Flight> (sorted by round, flight number)
 * - $scores: array<TableId|EntryId => Score>
 * - $currentIdentity: Identity
 */
?>
    <h1>Judging Scoresheet - <?= e($table->name()) ?></h1>

    <div class="scoresheet-info">
        <div class="info-item">
            <label>Table:</label>
            <span><?= e($table->name()) ?></span>
        </div>
        <div class="info-item">
            <label>Judge:</label>
            <span><?= e($currentIdentity->username ?? '') ?></span>
        </div>
        <div class="info-item">
            <label>Status:</label>
            <?php
            $stateLabelClass = match ($table->state()) {
                \Bcoem\Domain\Judging\ValueObject\TableState::Planning => 'default',
                \Bcoem\Domain\Judging\ValueObject\TableState::Active => 'primary',
                \Bcoem\Domain\Judging\ValueObject\TableState::Judged => 'success',
                \Bcoem\Domain\Judging\ValueObject\TableState::Locked => 'danger',
                \Bcoem\Domain\Judging\ValueObject\TableState::Archived => 'default',
            };
            ?>
            <span class="label label-<?= e($stateLabelClass) ?>">
                <?= e($table->state()->label()) ?>
            </span>
        </div>
    </div>

    <?php if (!$table->isReadyForJudging()): ?>
        <div class="alert alert-warning">
            <strong>Notice:</strong> Table is in <?= e($table->state()->label()) ?> state. Scoring is not available.
        </div>
    <?php elseif ($table->isLocked()): ?>
        <div class="alert alert-danger">
            <strong>Notice:</strong> Table is locked. No further scoring allowed.
        </div>
    <?php endif; ?>

    <?php if (empty($flights)): ?>
        <div class="alert alert-info">
            <strong>No flights scheduled.</strong> Flights will appear here once the admin adds them.
        </div>
    <?php else: ?>
        <form method="post" action="/judging/scores" class="scoresheet-form" id="scoresheet">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="table_id" value="<?= $table->id()->value() ?>">

            <table class="table">
                <thead>
                    <tr>
                        <th>Round</th>
                        <th>Flight #</th>
                        <th>Entry ID</th>
                        <th>Score (0-50)</th>
                        <th>Place</th>
                        <th>Type</th>
                        <th>Mini BoS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentRound = null;
                    foreach ($flights as $flight):
                        if ($currentRound !== $flight->round()):
                            $currentRound = $flight->round();
                            echo '<tr class="round-separator"><td colspan="7">Round ' . e($currentRound) . '</td></tr>';
                        endif;

                        $scoreKey = $flight->entryId()->value();
                        $existingScore = $scores[$scoreKey] ?? null;
                        $version = $existingScore ? $existingScore->version() : 0;
                        $scoreValue = $existingScore ? $existingScore->score() : '';
                        $place = $existingScore ? $existingScore->place() : '';
                        $scoreType = $existingScore ? $existingScore->scoreType() : 'regular';
                        $miniBos = $existingScore ? $existingScore->miniBos() : 0;
                    ?>
                        <tr class="score-row" data-entry-id="<?= $flight->entryId()->value() ?>">
                            <td><?= $flight->round() ?></td>
                            <td><?= $flight->flightNumber() ?></td>
                            <td><?= $flight->entryId()->value() ?></td>
                            <td>
                                <input type="hidden" name="entry_id[]" value="<?= $flight->entryId()->value() ?>">
                                <input type="hidden" name="version[]" value="<?= $version ?>">
                                <input type="number" name="score[]" class="score-input form-control" min="0" max="50" step="0.5"
                                    value="<?= e($scoreValue) ?>" placeholder="—"
                                    <?= !$table->isReadyForJudging() || $table->isLocked() ? 'disabled' : '' ?>>
                            </td>
                            <td>
                                <input type="number" name="place[]" class="place-input form-control" min="1" max="999"
                                    value="<?= e($place) ?>" placeholder="—"
                                    <?= !$table->isReadyForJudging() || $table->isLocked() ? 'disabled' : '' ?>>
                            </td>
                            <td>
                                <select name="score_type[]" class="score-type-select form-control"
                                    <?= !$table->isReadyForJudging() || $table->isLocked() ? 'disabled' : '' ?>>
                                    <option value="regular" <?= $scoreType === 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="mini-bos" <?= $scoreType === 'mini-bos' ? 'selected' : '' ?>>Mini BoS</option>
                                    <option value="bos" <?= $scoreType === 'bos' ? 'selected' : '' ?>>BoS</option>
                                </select>
                            </td>
                            <td>
                                <input type="checkbox" name="mini_bos[]" value="1"
                                    <?= $miniBos ? 'checked' : '' ?>
                                    <?= !$table->isReadyForJudging() || $table->isLocked() ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($table->isReadyForJudging() && !$table->isLocked()): ?>
                <div class="scoresheet-actions">
                    <button type="submit" class="btn btn-primary" name="action" value="save">
                        Save Scores
                    </button>
                    <button type="submit" class="btn btn-default" name="action" value="save-and-next" title="Save and move to next flight">
                        Save & Next
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
