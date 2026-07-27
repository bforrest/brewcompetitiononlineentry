<?php
declare(strict_types=1);
?>
<?php if ($view->contacts !== []): ?>
<section id="contact" class="container-xxl py-4" aria-labelledby="contacts-heading">
    <h2 id="contacts-heading"><?= e($view->copy->officials) ?></h2>
    <ul class="list-group list-group-flush">
        <?php foreach ($view->contacts as $contact): ?>
        <li class="list-group-item px-0 text-break">
            <?php if ($contact->destination !== null): ?><a class="link-dark" href="<?= e($contact->destination) ?>"><strong><?= e($contact->firstName . ' ' . $contact->lastName) ?></strong></a><?php else: ?><strong><?= e($contact->firstName . ' ' . $contact->lastName) ?></strong><?php endif; ?>
            &mdash; <?= e($contact->position) ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
