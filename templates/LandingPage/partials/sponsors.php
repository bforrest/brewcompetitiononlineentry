<?php
declare(strict_types=1);
?>
<?php if ($view->sponsors !== []): ?>
<section id="sponsors" class="container-xxl py-4" aria-labelledby="sponsors-heading">
    <h2 id="sponsors-heading"><?= e($view->copy->sponsors) ?></h2>
    <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach ($view->sponsors as $sponsor): ?>
        <article class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 card-title"><?= e($sponsor->name) ?></h3>
                    <?php if ($sponsor->imagePath !== null): ?><img class="img-fluid mb-3" src="<?= e($sponsor->imagePath) ?>" alt="<?= e($sponsor->name) ?> logo"><?php endif; ?>
                    <?php if ($sponsor->description !== null): ?><p class="card-text"><?= e($sponsor->description) ?></p><?php endif; ?>
                    <?php if ($sponsor->location !== null): ?><p class="card-text"><small class="text-body-secondary"><?= e($sponsor->location) ?></small></p><?php endif; ?>
                    <?php if ($sponsor->websiteUrl !== null): ?><a class="card-link" href="<?= e($sponsor->websiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(sprintf($view->copy->visitSponsor, $sponsor->name)) ?></a><?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
