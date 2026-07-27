<?php
declare(strict_types=1);
$statusLabel = static fn (\Bcoem\Domain\Shared\ValueObject\WindowStatus $status): string => match ($status) {
    \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming => $view->copy->statusUpcoming,
    \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open => $view->copy->statusOpen,
    \Bcoem\Domain\Shared\ValueObject\WindowStatus::Closed => $view->copy->statusClosed,
};
?>
<section id="at-a-glance" class="container-xxl py-4" aria-labelledby="glance-heading">
    <h2 id="glance-heading"><?= e($view->copy->atAGlance) ?></h2>
    <dl class="row mb-0">
        <dt class="col-sm-4"><?= e($view->copy->entries) ?></dt>
        <dd class="col-sm-8"><?= e((string) $view->capacity->entryCount) ?><?php if ($view->capacity->entryLimit !== null): ?> / <?= e((string) $view->capacity->entryLimit) ?><?php endif; ?></dd>
        <dt class="col-sm-4"><?= e($view->copy->paidEntries) ?></dt>
        <dd class="col-sm-8"><?= e((string) $view->capacity->paidEntryCount) ?><?php if ($view->capacity->paidEntryLimit !== null): ?> / <?= e((string) $view->capacity->paidEntryLimit) ?><?php endif; ?></dd>
        <dt class="col-sm-4"><?= e($view->copy->entryStatus) ?></dt><dd class="col-sm-8"><?= e($statusLabel($view->entryStatus)) ?></dd>
        <dt class="col-sm-4"><?= e($view->copy->dropoffStatus) ?></dt><dd class="col-sm-8"><?= e($statusLabel($view->dropoffStatus)) ?></dd>
        <dt class="col-sm-4"><?= e($view->copy->shippingStatus) ?></dt><dd class="col-sm-8"><?= e($statusLabel($view->shippingStatus)) ?></dd>
        <?php if ($view->locations->shippingEnabled && ($view->locations->shippingName !== null || $view->locations->shippingAddress !== null)): ?>
        <dt class="col-sm-4"><?= e($view->copy->shipping) ?></dt>
        <dd class="col-sm-8"><?php if ($view->locations->shippingName !== null): ?><strong><?= e($view->locations->shippingName) ?></strong><br><?php endif; ?><?= e($view->locations->shippingAddress) ?></dd>
        <?php endif; ?>
        <?php if ($view->locations->awardsLocationName !== null || $view->locations->awardsLocation !== null || $view->locations->awardsDetails !== null): ?>
        <dt class="col-sm-4"><?= e($view->copy->awards) ?></dt>
        <dd class="col-sm-8"><?php if ($view->locations->awardsLocationName !== null): ?><strong><?= e($view->locations->awardsLocationName) ?></strong><br><?php endif; ?><?= e($view->locations->awardsLocation) ?><?php if ($view->locations->awardsDetails !== null): ?><br><?= e($view->locations->awardsDetails) ?><?php endif; ?></dd>
        <?php endif; ?>
        <?php if ($view->dates?->awards !== null): ?>
        <dt class="col-sm-4"><?= e($view->copy->awardsTime) ?></dt>
        <dd class="col-sm-8"><time><?= e($view->dates->awards) ?></time></dd>
        <?php endif; ?>
    </dl>
</section>
