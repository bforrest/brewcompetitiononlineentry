<?php
/**
 * Admin: Create or edit judging table
 *
 * Available variables:
 * - $table: JudgingTable|null (null for create mode)
 * - $location: LocationId
 * - $locationName: string
 * - $isEditMode: bool
 */
?>
    <div class="breadcrumb">
        <a href="/judging/locations">Locations</a> /
        <a href="/judging/tables?location=<?= $location->value() ?>">Tables</a> /
        <span><?= $isEditMode ? 'Edit' : 'Create' ?></span>
    </div>

    <h1><?= $isEditMode ? 'Edit Table' : 'Create New Table' ?></h1>

    <form method="post" action="<?= $isEditMode ? '/judging/tables/' . $table->id()->value() : '/judging/tables' ?>">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-group">
            <label for="name">Table Name:</label>
            <input type="text" name="name" id="name" class="form-control" required maxlength="100"
                value="<?= $isEditMode ? e($table->name()) : '' ?>"
                placeholder="e.g., Table A, Table B">
            <small>A descriptive name for this judging table</small>
        </div>

        <div class="form-group">
            <label for="entry_limit">Entry Limit:</label>
            <input type="number" name="entry_limit" id="entry_limit" class="form-control" required min="1" max="999"
                value="<?= $isEditMode ? $table->entryLimit() : '10' ?>"
                placeholder="Maximum entries for this table">
            <small>Maximum number of entries that can be scheduled at this table</small>
        </div>

        <?php if ($isEditMode): ?>
            <div class="form-group">
                <label for="state">Table State:</label>
                <select name="state" id="state" class="form-control" disabled>
                    <option value="<?= e($table->state()->value) ?>" selected>
                        <?= e($table->state()->label()) ?>
                    </option>
                </select>
                <small>State can be changed from the table detail page</small>
            </div>
        <?php endif; ?>

        <input type="hidden" name="location_id" value="<?= $location->value() ?>">

        <div class="btn-toolbar">
            <button type="submit" class="btn btn-primary">
                <?= $isEditMode ? 'Update Table' : 'Create Table' ?>
            </button>
            <a href="/judging/tables?location=<?= $location->value() ?>" class="btn btn-default">
                Cancel
            </a>
        </div>
    </form>

    <?php if ($isEditMode && $table->isEditable()): ?>
        <div class="panel panel-danger">
            <div class="panel-heading">
                <h3 class="panel-title">Danger Zone</h3>
            </div>
            <div class="panel-body">
                <form method="post" action="/judging/tables/<?= $table->id()->value() ?>" style="display:inline;">
                    <input type="hidden" name="_method" value="DELETE">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Are you sure? This will delete the table and all its flights.')">
                        Delete Table
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
