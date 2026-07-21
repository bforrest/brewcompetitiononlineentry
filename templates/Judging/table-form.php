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
<div class="container">
    <div class="breadcrumb">
        <a href="/judging/locations">Locations</a> /
        <a href="/judging/tables?location=<?= $location->value() ?>">Tables</a> /
        <span><?= $isEditMode ? 'Edit' : 'Create' ?></span>
    </div>

    <h1><?= $isEditMode ? 'Edit Table' : 'Create New Table' ?></h1>

    <form method="post" action="<?= $isEditMode ? '/judging/tables/' . $table->id()->value() : '/judging/tables' ?>" class="table-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-group">
            <label for="name">Table Name:</label>
            <input type="text" name="name" id="name" required maxlength="100"
                value="<?= $isEditMode ? e($table->name()) : '' ?>"
                placeholder="e.g., Table A, Table B">
            <small>A descriptive name for this judging table</small>
        </div>

        <div class="form-group">
            <label for="entry_limit">Entry Limit:</label>
            <input type="number" name="entry_limit" id="entry_limit" required min="1" max="999"
                value="<?= $isEditMode ? $table->entryLimit() : '10' ?>"
                placeholder="Maximum entries for this table">
            <small>Maximum number of entries that can be scheduled at this table</small>
        </div>

        <?php if ($isEditMode): ?>
            <div class="form-group">
                <label for="state">Table State:</label>
                <select name="state" id="state" disabled>
                    <option value="<?= e($table->state()->value) ?>" selected>
                        <?= e($table->state()->label()) ?>
                    </option>
                </select>
                <small>State can be changed from the table detail page</small>
            </div>
        <?php endif; ?>

        <input type="hidden" name="location_id" value="<?= $location->value() ?>">

        <div class="form-actions">
            <button type="submit" class="button button-primary">
                <?= $isEditMode ? 'Update Table' : 'Create Table' ?>
            </button>
            <a href="/judging/tables?location=<?= $location->value() ?>" class="button button-secondary">
                Cancel
            </a>
        </div>
    </form>

    <?php if ($isEditMode && $table->isEditable()): ?>
        <div class="danger-zone">
            <h3>Danger Zone</h3>
            <form method="post" action="/judging/tables/<?= $table->id()->value() ?>" style="display:inline;">
                <input type="hidden" name="_method" value="DELETE">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="button button-danger"
                    onclick="return confirm('Are you sure? This will delete the table and all its flights.')">
                    Delete Table
                </button>
            </form>
        </div>
    <?php endif; ?>

    <style>
        .table-form {
            max-width: 500px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .danger-zone {
            margin-top: 40px;
            padding: 20px;
            border: 2px solid #dc3545;
            border-radius: 4px;
            background-color: #fff5f5;
        }

        .danger-zone h3 {
            color: #dc3545;
            margin-top: 0;
        }
    </style>
</div>
