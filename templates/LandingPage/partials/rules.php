<?php
declare(strict_types=1);
?>
<?php if ($view->rules !== null && $view->rules->competitionRules !== ''): ?>
<section id="rules" class="container-xxl py-4" aria-labelledby="rules-heading">
    <h2 id="rules-heading"><?= e($view->copy->rules) ?></h2>
    <p><?= nl2br(e($view->rules->competitionRules)) ?></p>
</section>
<?php endif; ?>
