<?php
declare(strict_types=1);
?>
<?php foreach ($view->alerts as $alert): ?>
<div class="container-xxl mt-3">
    <div class="alert alert-<?= e($alert->level->value) ?>" role="alert">
        <?= e($alert->message) ?>
        <?php if ($alert->linkUrl !== null && $alert->linkLabel !== null): ?>
        <a class="alert-link" href="<?= e($alert->linkUrl) ?>"<?php if ($alert->linkUrl === '#login-modal'): ?> data-bs-toggle="modal" data-bs-target="#login-modal" aria-controls="login-modal"<?php endif; ?>><?= e($alert->linkLabel) ?></a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
