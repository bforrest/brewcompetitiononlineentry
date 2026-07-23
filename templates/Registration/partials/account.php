<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<fieldset>
    <legend>Account details</legend>
    <div class="form-group<?= isset($form->fieldErrors['user_name']) ? ' has-error' : '' ?>">
        <label for="user_name" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Email</label>
        <div class="col-sm-6">
            <input class="form-control" id="user_name" name="user_name" type="email" value="<?= e((string) ($form->values['user_name'] ?? '')) ?>" required>
            <?php if (isset($form->fieldErrors['user_name'])): ?><span class="help-block"><?= e($form->fieldErrors['user_name']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="form-group<?= isset($form->fieldErrors['password']) ? ' has-error' : '' ?>">
        <label for="password" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Password</label>
        <div class="col-sm-6">
            <input class="form-control" id="password" name="password" type="password" required>
            <?php if (isset($form->fieldErrors['password'])): ?><span class="help-block"><?= e($form->fieldErrors['password']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="form-group">
        <label for="password-confirm" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Confirm password</label>
        <div class="col-sm-6"><input class="form-control" id="password-confirm" name="password-confirm" type="password" required></div>
    </div>
    <div class="form-group<?= isset($form->fieldErrors['userQuestion']) ? ' has-error' : '' ?>">
        <span class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Security question</span>
        <div class="col-sm-6">
            <?php foreach (['Favorite hop?', 'First brewing city?', 'Favorite beer style?'] as $question): ?>
                <label class="radio-inline"><input type="radio" name="userQuestion" value="<?= e($question) ?>"<?= ($form->values['userQuestion'] ?? 'Favorite hop?') === $question ? ' checked' : '' ?> required> <?= e($question) ?></label>
            <?php endforeach; ?>
            <?php if (isset($form->fieldErrors['userQuestion'])): ?><span class="help-block"><?= e($form->fieldErrors['userQuestion']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="form-group<?= isset($form->fieldErrors['userQuestionAnswer']) ? ' has-error' : '' ?>">
        <label for="userQuestionAnswer" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Security answer</label>
        <div class="col-sm-6">
            <input class="form-control" id="userQuestionAnswer" name="userQuestionAnswer" type="text" value="<?= e((string) ($form->values['userQuestionAnswer'] ?? '')) ?>" required>
            <?php if (isset($form->fieldErrors['userQuestionAnswer'])): ?><span class="help-block"><?= e($form->fieldErrors['userQuestionAnswer']) ?></span><?php endif; ?>
        </div>
    </div>
</fieldset>
