<?php
declare(strict_types=1);
?>
<section id="winners" class="container-xxl py-4" aria-labelledby="winners-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 id="winners-heading" class="mb-0"><?= e($view->copy->results) ?></h2>
        <?php if ($view->winnerResultsVisible): ?>
        <span>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($view->links->resultsPdf) ?>" target="_blank" rel="noopener noreferrer">PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($view->links->resultsHtml) ?>" target="_blank" rel="noopener noreferrer">HTML</a>
        </span>
        <?php endif; ?>
    </div>
    <?php if (!$view->judging->displayWinners): ?>
    <p class="mb-0"><?= e($view->copy->winnerDelayMessage) ?></p>
    <?php elseif ($view->winners->rows === []): ?>
    <p class="mb-0"><?= e($view->copy->winnerDelayMessage) ?></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th scope="col">Place</th><th scope="col">Entry</th><th scope="col">Brewer</th><th scope="col">Style</th><th scope="col">Score</th></tr></thead>
            <tbody>
            <?php foreach ($view->winners->rows as $winner): ?>
            <tr>
                <td><?= e((string) $winner->place) ?></td>
                <td><strong><?= e($winner->entryName) ?></strong><br><small><?= e($winner->groupName) ?></small></td>
                <td><?= e($winner->brewerName) ?><?php if ($winner->coBrewerName !== null): ?> &amp; <?= e($winner->coBrewerName) ?><?php endif; ?></td>
                <td><?= e($winner->style) ?><?php if ($winner->entryInfo !== null): ?><br><small><?= e($winner->entryInfo) ?></small><?php endif; ?></td>
                <td><?= e($winner->score === null ? '' : (string) $winner->score) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
