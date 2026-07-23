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

    <div>
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
        <p class="text-muted">No tables found for this location and state.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
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
                            <span class="label label-<?= e($table->state()->labelClass()) ?>">
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
