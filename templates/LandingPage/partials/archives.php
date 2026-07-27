<?php
declare(strict_types=1);
?>
<?php if ($view->archives !== []): ?>
<div class="offcanvas offcanvas-end" data-bs-scroll="true" data-bs-theme="dark" tabindex="-1" id="archive-list" aria-labelledby="archive-list-label">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="archive-list-label">Past Winners</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="list-unstyled mb-0">
            <?php foreach ($view->archives as $archive): ?>
            <li><a href="/index.php?section=past_winners&amp;go=<?= e(rawurlencode($archive->suffix)) ?>"><?= e($archive->suffix) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
