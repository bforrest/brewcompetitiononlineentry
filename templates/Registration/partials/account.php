<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<?php
$fallbackQuestions = [
    'What is your favorite all-time beer to drink?',
    'What was the first name of your first girlfriend or boyfriend?',
    'What was the last name of your third grade teacher?',
    'In what city or town did you meet your significant other?',
    'What was the first name of your best friend in sixth grade?',
    'Where were you when you had your first kiss?',
    'What is the name of a college you applied to but did not attend?',
    'What was the name of your first stuffed animal, doll, or action figure?',
    'What street did you live on in first grade?',
    'What is the name of your favorite cancelled TV show?',
];
$questions = $options->securityQuestions !== [] ? $options->securityQuestions : $fallbackQuestions;
$selectedQuestion = (string) ($form->values['userQuestion'] ?? $questions[0]);
?>
<div class="row mb-3<?= isset($form->fieldErrors['user_name']) ? ' has-error' : '' ?>">
    <label for="user_name" class="col-xs-12 col-sm-3 col-lg-2 col-form-label text-teal"><i class="fa fa-star me-1"></i><strong>Email Address</strong></label>
    <div class="col-xs-12 col-sm-9 col-lg-10"><input class="form-control" id="user_name" name="user_name" type="email" value="<?= e((string) ($form->values['user_name'] ?? '')) ?>" autofocus required><?php if (isset($form->fieldErrors['user_name'])): ?><div class="help-block invalid-feedback text-danger"><?= e($form->fieldErrors['user_name']) ?></div><?php endif; ?><div id="username-status" class="mt-2"></div></div>
</div>
<div class="row mb-3<?= isset($form->fieldErrors['password']) ? ' has-error' : '' ?>">
    <label for="password-entry" class="col-xs-12 col-sm-3 col-lg-2 col-form-label text-teal"><i class="fa fa-star me-1"></i><strong>Password</strong></label>
    <div class="col-xs-12 col-sm-9 col-lg-10"><input class="form-control" id="password-entry" name="password" type="password" placeholder="Password" required><?php if (isset($form->fieldErrors['password'])): ?><div class="help-block invalid-feedback text-danger"><?= e($form->fieldErrors['password']) ?></div><?php endif; ?></div>
</div>
<div class="row mb-3" id="pwd-container"><label class="col-xs-12 col-sm-3 col-lg-2 col-form-label"><strong>Password Strength</strong></label><div class="col-lg-10 col-md-9 col-sm-8 col-xs-12"><div class="pwd-strength-viewport-progress"></div><div id="length-help-text" class="small"></div></div></div>
<div class="row mb-3<?= isset($form->fieldErrors['password-confirm']) ? ' has-error' : '' ?>">
    <label for="password-confirm" class="col-xs-12 col-sm-3 col-lg-2 col-form-label text-teal"><strong><i class="fa fa-star me-2"></i>Confirm Password</strong></label>
    <div class="col-lg-10 col-md-9 col-sm-8 col-xs-12"><input class="form-control password-field" name="password-confirm" id="password-confirm" type="password" required><?php if (isset($form->fieldErrors['password-confirm'])): ?><div class="help-block mt-1 text-danger"><?= e($form->fieldErrors['password-confirm']) ?></div><?php endif; ?><div id="password-error" class="help-block mt-1 text-danger"></div></div>
</div>
<div class="mb-3 row<?= isset($form->fieldErrors['userQuestion']) ? ' has-error' : '' ?>"><label for="security" class="col-xs-12 col-sm-3 col-lg-2 col-form-label text-teal"><i class="fa fa-star me-1"></i><strong>Security Question</strong></label><div class="col-xs-12 col-sm-9 col-lg-10"><?php foreach ($questions as $question): ?><div class="form-check"><input class="form-check-input" type="radio" name="userQuestion" value="<?= e($question) ?>"<?= $selectedQuestion === $question ? ' checked' : '' ?>><label class="form-check-label"><?= e($question) ?></label></div><?php endforeach; ?><div class="help-block">Choose one. This question will be used to verify your identity should you forget your password.</div></div></div>
<div class="mb-3 row<?= isset($form->fieldErrors['userQuestionAnswer']) ? ' has-error' : '' ?>"><label for="userQuestionAnswer" class="col-xs-12 col-sm-3 col-lg-2 col-form-label text-teal"><i class="fa fa-star me-1"></i><strong>Security Question Answer</strong></label><div class="col-xs-12 col-sm-9 col-lg-10"><input class="form-control" name="userQuestionAnswer" id="userQuestionAnswer" type="text" value="<?= e((string) ($form->values['userQuestionAnswer'] ?? '')) ?>" required><div class="help-block">Make your security answer something only you will easily remember!</div><?php if (isset($form->fieldErrors['userQuestionAnswer'])): ?><div class="help-block text-danger"><?= e($form->fieldErrors['userQuestionAnswer']) ?></div><?php endif; ?></div></div>
