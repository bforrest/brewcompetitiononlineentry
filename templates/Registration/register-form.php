<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<section id="login" class="landing-page-section pb-3">
<header class="landing-page-section-header py-2"><h1>Register</h1></header>
<p class="lead">The information you provide beyond your first name, last name, and club is strictly for record-keeping and contact purposes. <small>A condition of entry into the competition is providing this information. Your name and club may be displayed should one of your entries place, but no other information will be made public.</small></p>
<p>To register, create your account by filling out the fields below.</p>
<form id="submit-form" class="form-horizontal needs-validation hide-loader-form-submit" method="post" action="/register" name="register_form" novalidate>
    <input type="hidden" name="userLevel" value="2">
    <input type="hidden" name="brewerJudge" value="N">
    <input type="hidden" name="brewerSteward" value="N">
    <?php require __DIR__ . '/partials/errors.php'; ?>
    <?php require __DIR__ . '/partials/account.php'; ?>
    <?php require __DIR__ . '/partials/contact-address.php'; ?>
    <?php require __DIR__ . '/partials/logistics.php'; ?>
    <?php require __DIR__ . '/partials/volunteer.php'; ?>
    <?php require __DIR__ . '/partials/submit.php'; ?>
</form>
<script src="/js_includes/registration-public.js" defer></script>
</section>
