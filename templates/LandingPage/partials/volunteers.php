<?php
declare(strict_types=1);
?>
<section id="volunteers" class="container-xxl py-4" aria-labelledby="volunteers-heading">
    <header class="landing-page-section-header"><h2 id="volunteers-heading" class="fs-1 fw-bold"><?= e($view->copy->volunteers) ?></h2></header>
    <?php if ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p class="mb-0"><?= e($view->copy->judgeOpenMessage) ?></p>
    <?php elseif ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming): ?>
    <p class="mb-0"><?= e($view->copy->judgeUpcomingMessage) ?></p>
    <?php else: ?>
    <p class="mb-0"><?= e($view->copy->closedMessage) ?></p>
    <?php endif; ?>
</section>
