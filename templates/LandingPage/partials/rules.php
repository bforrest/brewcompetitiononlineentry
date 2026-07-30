<?php
declare(strict_types=1);
?>
<?php if ($view->rules !== null && $view->rules->competitionRules !== ''): ?>
<section id="rules" class="container-xxl py-4" aria-labelledby="rules-heading">
    <header class="landing-page-section-header"><h2 id="rules-heading" class="fs-1 fw-bold"><?= e($view->copy->rules) ?></h2></header>
    <p><?= nl2br(e($view->rules->competitionRules)) ?></p>
</section>
<?php endif; ?>
