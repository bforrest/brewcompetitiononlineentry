<?php
declare(strict_types=1);
?>
<?php if (!$view->loggedIn): ?>
<div class="modal fade" id="login-modal" tabindex="-1" aria-labelledby="login-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="login-modal-label"><?= e($view->copy->login) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="/includes/process.inc.php?section=login&amp;action=login" class="needs-validation">
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input class="form-control" id="login-user-name" name="loginUsername" type="email" aria-describedby="login-user-name-feedback" required>
                        <label for="login-user-name">Email Address</label>
                        <div class="invalid-feedback" id="login-user-name-feedback">A valid email is required.</div>
                    </div>
                    <div class="form-floating mb-3">
                        <input class="form-control" id="login-password" name="loginPassword" type="password" aria-describedby="login-password-feedback" required>
                        <label for="login-password">Password</label>
                        <div class="invalid-feedback" id="login-password-feedback">Password is required.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" type="submit"><?= e($view->copy->login) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
