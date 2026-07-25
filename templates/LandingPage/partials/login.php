<?php
declare(strict_types=1);
?>
<?php if (!$view->loggedIn): ?>
<section id="login" class="container-xxl py-4" aria-labelledby="login-heading">
    <h2 id="login-heading"><?= e($view->copy->login) ?></h2>
    <form method="post" action="/includes/process.inc.php?section=login&amp;action=login" class="needs-validation" novalidate>
        <div class="form-floating mb-3">
            <input class="form-control" id="login-user-name" name="loginUsername" type="email" required>
            <label for="login-user-name">Email</label>
            <div class="invalid-feedback d-block">A valid email is required.</div>
        </div>
        <div class="form-floating mb-3">
            <input class="form-control" id="login-password" name="loginPassword" type="password" required>
            <label for="login-password">Password</label>
            <div class="invalid-feedback d-block">Password is required.</div>
        </div>
        <button class="btn btn-primary" type="submit"><?= e($view->copy->login) ?></button>
    </form>
</section>
<?php endif; ?>
