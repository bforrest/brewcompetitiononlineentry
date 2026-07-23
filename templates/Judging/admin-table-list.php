<?php
/**
 * Admin: List judging tables at a location
 *
 * Available variables:
 * - $tables: array<JudgingTable>
 * - $location: LocationId
 * - $locationName: string
 * - $states: TableState[]
 * - $selectedState: TableState|null
 */
?>
<h1><?= e($locationName) ?> - Judging Tables</h1>

    <div class="judging-controls">
        <form method="get" class="state-filter">
            <label for="state">Filter by state:</label>
            <select name="state" id="state" onchange="this.form.submit()">
                <option value="">All states</option>
                <?php foreach ($states as $state): ?>
                    <option value="<?= e($state->value) ?>" <?= $selectedState?->value === $state->value ? 'selected' : '' ?>>
                        <?= e($state->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <a href="/judging/tables/create?location=<?= e($location->value()) ?>" class="btn btn-primary">
            Create New Table
        </a>
    </div>

    <?php if (empty($tables)): ?>
        <p class="empty-state">No tables found for this location and state.</p>
    <?php else: ?>
        <table class="tables-list">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>State</th>
                    <th>Flights</th>
                    <th>Entry Limit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table): ?>
                    <tr>
                        <td><?= e($table->name()) ?></td>
                        <td>
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
                        </td>
                        <td><?= $table->flights()->count() ?></td>
                        <td><?= $table->entryLimit() ?></td>
                        <td>
                            <a href="/judging/tables/<?= $table->id()->value() ?>" class="btn">
                                View
                            </a>
                            <?php if ($table->isEditable()): ?>
                                <a href="/judging/tables/<?= $table->id()->value() ?>/edit" class="btn">
                                    Edit
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
