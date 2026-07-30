<?php
declare(strict_types=1);
?>
<section id="entry-info" class="container-xxl py-4" aria-labelledby="entry-info-heading">
    <header class="landing-page-section-header"><h2 id="entry-info-heading" class="fs-1 fw-bold"><?= e($view->copy->entryInfo) ?></h2></header>
    <?php if ($view->rules?->entryAcceptanceRules !== null): ?>
    <h3 class="h5"><?= e($view->copy->entryAcceptance) ?></h3>
    <p><?= nl2br(e($view->rules->entryAcceptanceRules)) ?></p>
    <?php endif; ?>

    <?php if ($view->locations->shippingEnabled && ($view->locations->shippingName !== null || $view->locations->shippingAddress !== null)): ?>
    <h3 class="h5"><?= e($view->copy->shipping) ?></h3>
    <p><?php if ($view->locations->shippingName !== null): ?><strong><?= e($view->locations->shippingName) ?></strong><br><?php endif; ?><?= e($view->locations->shippingAddress) ?></p>
    <?php endif; ?>

    <?php if ($view->locations->dropoffLocations !== []): ?>
    <h3 class="h5"><?= e($view->copy->dropoffLocations) ?></h3>
    <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach ($view->locations->dropoffLocations as $location): ?>
        <article class="col">
            <div class="card h-100"><div class="card-body">
                <h4 class="h6 card-title"><?php if ($location->websiteUrl !== null): ?><a href="<?= e($location->websiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($location->name) ?></a><?php else: ?><?= e($location->name) ?><?php endif; ?></h4>
                <p class="card-text mb-1"><?= e($location->address) ?></p>
                <?php if ($location->phone !== null): ?><p class="card-text mb-1"><?= e($location->phone) ?></p><?php endif; ?>
                <?php if ($location->notes !== null): ?><p class="card-text"><em><?= e($location->notes) ?></em></p><?php endif; ?>
            </div></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
