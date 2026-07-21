<?php
/**
 * Create Entry Form
 *
 * Variables passed from controller:
 * - $styles: array of available styles
 * - $maxEntries: brewer's entry limit
 * - $currentEntryCount: entries already submitted
 * - $entryWindow: window status (1=open, 0=closed, 2=past deadline)
 * - $errors: array of validation errors (field => message)
 */
require_once __DIR__ . '/helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Entry</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2em auto; }
        .form-group { margin-bottom: 1.5em; }
        label { display: block; margin-bottom: 0.5em; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 0.5em; box-sizing: border-box; }
        .error { color: #d32f2f; font-size: 0.9em; margin-top: 0.25em; }
        button { background: #1976d2; color: white; padding: 0.75em 1.5em; border: none; cursor: pointer; }
        button:hover { background: #1565c0; }
        .alert { padding: 1em; margin-bottom: 1em; border-radius: 4px; }
        .alert-danger { background: #ffebee; color: #c62828; }
        .alert-info { background: #e3f2fd; color: #0d47a1; }
    </style>
</head>
<body>
    <h1>Create Entry</h1>

    <?php if ($entryWindow !== 1): ?>
        <div class="alert alert-danger">
            Entry submission window is currently closed. Please try again later.
        </div>
    <?php endif; ?>

    <?php if ($currentEntryCount >= $maxEntries): ?>
        <div class="alert alert-danger">
            You have reached your entry limit (<?= e((string) $maxEntries) ?> entries).
        </div>
    <?php endif; ?>

    <form method="POST" action="/entries">
        <div class="form-group">
            <label for="brewName">Entry Name <span style="color: red;">*</span></label>
            <input type="text" id="brewName" name="brewName" required maxlength="255"
                   value="<?= e($_POST['brewName'] ?? '') ?>">
            <?php if (isset($errors['brewName'])): ?>
                <div class="error"><?= e($errors['brewName']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brewCategorySort">Style <span style="color: red;">*</span></label>
            <select id="brewCategorySort" name="brewCategorySort" required>
                <option value="">-- Select a style --</option>
                <?php foreach ($styles ?? [] as $style): ?>
                    <option value="<?= e($style['brewCategorySort']) ?>"
                            <?= ($_POST['brewCategorySort'] ?? '') === $style['brewCategorySort'] ? 'selected' : '' ?>>
                        <?= e($style['brewCategorySort'] ?? '') ?> - <?= e($style['brewCategoryName'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['brewCategorySort'])): ?>
                <div class="error"><?= e($errors['brewCategorySort']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brewSubCategory">Subcategory <span style="color: red;">*</span></label>
            <input type="text" id="brewSubCategory" name="brewSubCategory" required maxlength="10"
                   value="<?= e($_POST['brewSubCategory'] ?? '') ?>">
            <small>e.g., A, B, C, etc.</small>
            <?php if (isset($errors['brewSubCategory'])): ?>
                <div class="error"><?= e($errors['brewSubCategory']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brewComments">Comments</label>
            <textarea id="brewComments" name="brewComments" rows="4" maxlength="500"><?= e($_POST['brewComments'] ?? '') ?></textarea>
            <?php if (isset($errors['brewComments'])): ?>
                <div class="error"><?= e($errors['brewComments']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brewABV">ABV (Alcohol by Volume)</label>
            <input type="text" id="brewABV" name="brewABV" pattern="\d+(\.\d{1,2})?"
                   placeholder="e.g., 5.5" value="<?= e($_POST['brewABV'] ?? '') ?>">
            <?php if (isset($errors['brewABV'])): ?>
                <div class="error"><?= e($errors['brewABV']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="brewCoBrewer">Co-Brewer Name</label>
            <input type="text" id="brewCoBrewer" name="brewCoBrewer" maxlength="100"
                   value="<?= e($_POST['brewCoBrewer'] ?? '') ?>">
        </div>

        <button type="submit">Create Entry</button>
        <a href="/entries/my" style="margin-left: 1em; text-decoration: none; color: #666;">Cancel</a>
    </form>

    <p style="margin-top: 2em; color: #666; font-size: 0.9em;">
        Entries submitted: <?= e((string) ($currentEntryCount ?? 0)) ?> / <?= e((string) ($maxEntries ?? 5)) ?>
    </p>
</body>
</html>
