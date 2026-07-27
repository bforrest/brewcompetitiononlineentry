<?php
declare(strict_types=1);
?>
<section id="registration" class="container-xxl py-4" aria-labelledby="registration-heading">
    <h2 id="registration-heading"><?= e($view->copy->register) ?></h2>
    <?php if ($view->registrationStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming): ?>
    <p class="lead"><?= e($view->copy->upcomingMessage) ?></p>
    <?php elseif ($view->registrationStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p class="lead"><?= e($view->copy->openMessage) ?></p>
    <?php else: ?>
    <p class="lead"><?= e($view->copy->closedMessage) ?></p>
    <?php endif; ?>

    <?php if ($view->actions !== null): ?>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ([$view->actions->account, $view->actions->entry, $view->actions->judge, $view->actions->steward] as $action): ?>
        <?php if ($action !== null): ?><a class="btn btn-primary" href="<?= e($action->url) ?>"<?php if ($action->url === '#login-modal'): ?> data-bs-toggle="modal" data-bs-target="#login-modal"<?php endif; ?>><?= e($action->label) ?></a><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($view->dates !== null): ?>
    <?php
    $dateRows = [
        [$view->copy->registrationDates, $view->dates->registration],
        [$view->copy->entryDates, $view->dates->entries],
        [$view->copy->judgeDates, $view->dates->judges],
        [$view->copy->dropoffDates, $view->dates->dropoff],
        [$view->copy->shippingDates, $view->dates->shipping],
    ];
    ?>
    <dl class="row mb-0">
        <?php foreach ($dateRows as [$label, $range]): ?>
        <?php if ($range->opens !== null || $range->closes !== null): ?>
        <dt class="col-md-4"><?= e($label) ?></dt>
        <dd class="col-md-8">
            <?php if ($range->opens !== null): ?><span><?= e($view->copy->opens) ?> <time<?php if ($range->opensAt !== null): ?> data-timestamp="<?= e((string) $range->opensAt) ?>"<?php endif; ?>><?= e($range->opens) ?></time></span><?php endif; ?>
            <?php if ($range->opens !== null && $range->closes !== null): ?><br><?php endif; ?>
            <?php if ($range->closes !== null): ?><span><?= e($view->copy->closes) ?> <time<?php if ($range->closesAt !== null): ?> data-timestamp="<?= e((string) $range->closesAt) ?>"<?php endif; ?>><?= e($range->closes) ?></time></span><?php endif; ?>
        </dd>
        <?php endif; ?>
        <?php endforeach; ?>
    </dl>
    <?php endif; ?>
</section>
<?php if ($view->sections?->volunteers ?? true): ?>
<section id="volunteers" class="container-xxl py-4" aria-labelledby="volunteers-heading">
    <h2 id="volunteers-heading"><?= e($view->copy->volunteers) ?></h2>
    <?php if ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p class="mb-0"><?= e($view->copy->judgeOpenMessage) ?></p>
    <?php elseif ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming): ?>
    <p class="mb-0"><?= e($view->copy->upcomingMessage) ?></p>
    <?php else: ?>
    <p class="mb-0"><?= e($view->copy->closedMessage) ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>
