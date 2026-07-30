<?php
declare(strict_types=1);
?>
<main id="main-content" data-modern-landing-page="true">
    <?php require __DIR__ . '/partials/alerts.php'; ?>
    <?php if ($view->sections?->atAGlance ?? true): ?>
    <?php require __DIR__ . '/partials/at-a-glance.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->volunteers ?? true): ?>
    <?php require __DIR__ . '/partials/volunteers.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->rules ?? true): ?>
    <?php require __DIR__ . '/partials/rules.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->entryInfo ?? true): ?>
    <?php require __DIR__ . '/partials/entry-info.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->winners ?? true): ?>
    <?php require __DIR__ . '/partials/winners.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->contact ?? true): ?>
    <?php require __DIR__ . '/partials/contacts.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->sponsors ?? true): ?>
    <?php require __DIR__ . '/partials/sponsors.php'; ?>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/login.php'; ?>
    <?php require __DIR__ . '/partials/archives.php'; ?>
</main>
