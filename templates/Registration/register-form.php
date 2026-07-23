<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<h1>Register</h1>
<p class="lead">The information you provide beyond your first name, last name, and club is strictly for record-keeping and contact purposes. <small>A condition of entry into the competition is providing this information. Your name and club may be displayed should one of your entries place, but no other information will be made public.</small></p>
<p>To register, create your account by filling out the fields below.</p>
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
<script src="/js_includes/registration-public.js" defer></script>
