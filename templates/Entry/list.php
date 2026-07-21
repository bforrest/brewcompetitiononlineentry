<?php
/**
 * Entry List
 *
 * Variables passed from controller:
 * - $entries: array of Entry aggregates
 * - $page: current page number
 * - $totalPages: total number of pages
 * - $totalEntries: total entry count
 * - $maxEntries: brewer's entry limit
 */
require_once __DIR__ . '/../helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Entries</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #1976d2; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2em; }
        a.btn { display: inline-block; padding: 0.75em 1.5em; background: #1976d2; color: white; text-decoration: none; border-radius: 4px; }
        a.btn:hover { background: #1565c0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2em; }
        th, td { padding: 0.75em; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .status { display: inline-block; padding: 0.25em 0.75em; border-radius: 3px; font-size: 0.85em; }
        .status.paid { background: #c8e6c9; color: #2e7d32; }
        .status.unpaid { background: #ffccbc; color: #d84315; }
        .status.confirmed { background: #b3e5fc; color: #01579b; }
        .status.unconfirmed { background: #ffe0b2; color: #e65100; }
        .actions { white-space: nowrap; }
        .actions a { padding: 0.5em 1em; margin-right: 0.5em; text-decoration: none; color: #1976d2; }
        .actions a:hover { text-decoration: underline; }
        .pagination { text-align: center; margin-top: 2em; }
        .pagination a, .pagination span { padding: 0.5em 1em; margin: 0 0.25em; text-decoration: none; background: #f5f5f5; display: inline-block; }
        .pagination a:hover { background: #e0e0e0; }
        .pagination .current { background: #1976d2; color: white; }
        .empty { text-align: center; padding: 2em; color: #666; }
        .counter { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>My Entries</h1>
                <p class="counter">
                    Entries submitted: <strong><?= e((string) ($totalEntries ?? 0)) ?></strong> /
                    <strong><?= e((string) ($maxEntries ?? 5)) ?></strong>
                </p>
            </div>
            <a href="/entries" class="btn">+ New Entry</a>
        </div>

        <?php if (empty($entries)): ?>
            <div class="empty">
                <p>You haven't submitted any entries yet.</p>
                <a href="/entries" class="btn">Create Your First Entry</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Entry Name</th>
                        <th>Style</th>
                        <th>Status</th>
                        <th>Paid</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= e($entry->name()) ?></td>
                            <td><?= e($entry->style()->format()) ?></td>
                            <td>
                                <span class="status <?= $entry->isConfirmed() ? 'confirmed' : 'unconfirmed' ?>">
                                    <?= $entry->isConfirmed() ? 'Confirmed' : 'Unconfirmed' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status <?= $entry->isPaid() ? 'paid' : 'unpaid' ?>">
                                    <?= $entry->isPaid() ? 'Paid' : 'Unpaid' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="/entries/<?= e((string) $entry->id()->value()) ?>/edit">Edit</a>
                                <a href="#" onclick="alert('Delete via edit form'); return false;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="/entries/my?page=1">First</a>
                        <a href="/entries/my?page=<?= e((string) ($page - 1)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= e((string) $i) ?></span>
                        <?php else: ?>
                            <a href="/entries/my?page=<?= e((string) $i) ?>"><?= e((string) $i) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="/entries/my?page=<?= e((string) ($page + 1)) ?>">Next</a>
                        <a href="/entries/my?page=<?= e((string) $totalPages) ?>">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
