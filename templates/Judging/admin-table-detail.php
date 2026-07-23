<?php
/**
 * Admin: Table detail with flights and state transition
 *
 * Available variables:
 * - $table: JudgingTable
 * - $flights: array<Flight>
 * - $scores: array<Score>
 * - $allowedTransitions: TableState[]
 * - $currentIdentity: Identity
 */
?>
    <div class="breadcrumb">
        <a href="/judging/locations">Locations</a> /
        <a href="/judging/tables?location=<?= $table->location()->value() ?>">Tables</a> /
        <span><?= e($table->name()) ?></span>
    </div>

    <div class="table-header">
        <h1><?= e($table->name()) ?></h1>
        <span class="label label-<?= e(str_replace('badge-', '', $table->state()->cssClass())) ?>">
            <?= e($table->state()->label()) ?>
        </span>
    </div>

    <div class="table-info">
        <div class="info-group">
            <label>Entry Limit:</label>
            <span><?= $table->entryLimit() ?></span>
        </div>
        <div class="info-group">
            <label>Current Flights:</label>
            <span><?= $table->flights()->count() ?></span>
        </div>
        <div class="info-group">
            <label>Scores Recorded:</label>
            <span><?= count($scores) ?></span>
        </div>
    </div>

    <!-- Flights Section -->
    <div class="section">
        <h2>Flights</h2>
        <?php if ($table->isEditable()): ?>
            <form method="post" action="/judging/tables/<?= $table->id()->value() ?>/flights" class="add-flight-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

                <div class="form-group">
                    <label for="entry_id">Entry ID:</label>
                    <input type="number" name="entry_id" id="entry_id" required min="1">
                </div>

                <div class="form-group">
                    <label for="flight_number">Flight Number:</label>
                    <input type="number" name="flight_number" id="flight_number" required min="1">
                </div>

                <div class="form-group">
                    <label for="round">Round:</label>
                    <input type="number" name="round" id="round" required min="1">
                </div>

                <button type="submit" class="btn btn-primary">Add Flight</button>
            </form>
        <?php endif; ?>

        <?php if (empty($flights)): ?>
            <p class="empty-state">No flights scheduled for this table.</p>
        <?php else: ?>
            <table class="flights-table">
                <thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Flight #</th>
                        <th>Round</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flights as $flight): ?>
                        <tr>
                            <td><?= $flight->entryId()->value() ?></td>
                            <td><?= $flight->flightNumber() ?></td>
                            <td><?= $flight->round() ?></td>
                            <td>
                                <?php if ($table->isEditable()): ?>
                                    <form method="post" action="/judging/tables/<?= $table->id()->value() ?>/flights/<?= $flight->id()->value() ?>" style="display:inline;">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Remove this flight?')">
                                            Remove
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Scores Section -->
    <div class="section">
        <h2>Scores (<?= count($scores) ?>)</h2>
        <?php if (empty($scores)): ?>
            <p class="empty-state">No scores recorded yet.</p>
        <?php else: ?>
            <table class="scores-table">
                <thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Score</th>
                        <th>Place</th>
                        <th>Type</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $score): ?>
                        <tr>
                            <td><?= $score->entryId()->value() ?></td>
                            <td><?= number_format($score->score(), 2) ?></td>
                            <td><?= e($score->place() ?? '—') ?></td>
                            <td><?= e($score->scoreType()) ?></td>
                            <td><?= $score->version() ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- State Transition Section -->
    <?php if (!empty($allowedTransitions)): ?>
        <div class="section">
            <h2>State Transition</h2>
            <p>Current state: <strong><?= e($table->state()->label()) ?></strong></p>

            <form method="post" action="/judging/tables/<?= $table->id()->value() ?>/state">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

                <div class="form-group">
                    <label for="state">Transition to:</label>
                    <select name="state" id="state" required>
                        <option value="">-- Select state --</option>
                        <?php foreach ($allowedTransitions as $state): ?>
                            <option value="<?= e($state->value) ?>">
                                <?= e($state->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Transition State</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a href="/judging/tables?location=<?= $table->location()->value() ?>" class="btn btn-default">
            Back to Tables
        </a>
    </div>
