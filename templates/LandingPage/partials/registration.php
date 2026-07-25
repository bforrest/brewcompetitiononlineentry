<?php
declare(strict_types=1);
?>
<section id="entry-info" class="container-xxl py-4" aria-labelledby="registration-heading">
    <h2 id="registration-heading"><?= e($view->copy->register) ?></h2>
    <?php if ($view->registrationStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming): ?>
    <p class="lead"><?= e($view->copy->upcomingMessage) ?></p>
    <?php elseif ($view->registrationStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p class="lead"><?= e($view->copy->openMessage) ?></p>
    <?php if (!$view->loggedIn): ?>
    <a class="btn btn-primary" href="<?= e($view->links->register) ?>"><?= e($view->copy->register) ?></a>
    <?php endif; ?>
    <?php else: ?>
    <p class="lead"><?= e($view->copy->closedMessage) ?></p>
    <?php endif; ?>

    <?php if ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p><?= e($view->copy->judgeOpenMessage) ?></p>
    <?php endif; ?>

</section>
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
