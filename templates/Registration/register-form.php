<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<h1><?= e($options->title) ?></h1>
<?php if ($options->guidance !== ''): ?>
    <p class="lead"><?= e($options->guidance) ?></p>
<?php endif; ?>
<form id="register-form" class="form-horizontal" method="post" action="/register" novalidate>
    <?php require __DIR__ . '/partials/errors.php'; ?>
    <p class="text-warning"><span aria-hidden="true">*</span> Required information</p>
    <?php require __DIR__ . '/partials/account.php'; ?>
    <?php require __DIR__ . '/partials/contact-address.php'; ?>
    <?php require __DIR__ . '/partials/logistics.php'; ?>
    <?php require __DIR__ . '/partials/volunteer.php'; ?>
    <?php require __DIR__ . '/partials/waiver.php'; ?>
    <?php require __DIR__ . '/partials/submit.php'; ?>
</form>
