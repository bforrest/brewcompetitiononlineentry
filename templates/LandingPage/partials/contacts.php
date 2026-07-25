<?php
declare(strict_types=1);
?>
<?php if ($view->contacts !== []): ?>
<section id="contact" class="container-xxl py-4" aria-labelledby="contacts-heading">
    <h2 id="contacts-heading"><?= e($view->copy->officials) ?></h2>
    <ul class="list-group list-group-flush">
        <?php foreach ($view->contacts as $contact): ?>
        <li class="list-group-item px-0"><strong><?= e($contact->firstName . ' ' . $contact->lastName) ?></strong> &mdash; <?= e($contact->position) ?> (<?= e($contact->email) ?>)</li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
