<?php
declare(strict_types=1);
?>
<section id="rules" class="container-xxl py-4" aria-labelledby="glance-heading">
    <h2 id="glance-heading">At a glance</h2>
    <dl class="row mb-0">
        <dt class="col-sm-4">Entries</dt>
        <dd class="col-sm-8"><?= e((string) $view->capacity->entryCount) ?><?php if ($view->capacity->entryLimit !== null): ?> / <?= e((string) $view->capacity->entryLimit) ?><?php endif; ?></dd>
        <dt class="col-sm-4">Paid entries</dt>
        <dd class="col-sm-8"><?= e((string) $view->capacity->paidEntryCount) ?><?php if ($view->capacity->paidEntryLimit !== null): ?> / <?= e((string) $view->capacity->paidEntryLimit) ?><?php endif; ?></dd>
        <dt class="col-sm-4">Entry status</dt><dd class="col-sm-8"><?= e($view->entryStatus->name) ?></dd>
        <dt class="col-sm-4">Drop-off status</dt><dd class="col-sm-8"><?= e($view->dropoffStatus->name) ?></dd>
        <dt class="col-sm-4">Shipping status</dt><dd class="col-sm-8"><?= e($view->shippingStatus->name) ?></dd>
        <?php if ($view->locations->shippingName !== null || $view->locations->shippingAddress !== null): ?>
        <dt class="col-sm-4">Shipping</dt>
        <dd class="col-sm-8"><?php if ($view->locations->shippingName !== null): ?><strong><?= e($view->locations->shippingName) ?></strong><br><?php endif; ?><?= e($view->locations->shippingAddress) ?></dd>
        <?php endif; ?>
        <?php if ($view->locations->awardsLocationName !== null || $view->locations->awardsLocation !== null || $view->locations->awardsDetails !== null): ?>
        <dt class="col-sm-4">Awards</dt>
        <dd class="col-sm-8"><?php if ($view->locations->awardsLocationName !== null): ?><strong><?= e($view->locations->awardsLocationName) ?></strong><br><?php endif; ?><?= e($view->locations->awardsLocation) ?><?php if ($view->locations->awardsDetails !== null): ?><br><?= e($view->locations->awardsDetails) ?><?php endif; ?></dd>
        <?php endif; ?>
        <?php if ($view->locations->awardsAt !== null): ?>
        <dt class="col-sm-4">Awards time</dt>
        <dd class="col-sm-8"><time datetime="<?= e(gmdate('c', $view->locations->awardsAt)) ?>"><?= e(gmdate('F j, Y g:i A \\U\\T\\C', $view->locations->awardsAt)) ?></time></dd>
        <?php endif; ?>
    </dl>
</section>
