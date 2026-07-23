<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<?php if ($form->generalErrors !== [] || $form->fieldErrors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Please correct the following:</strong>
        <ul>
            <?php foreach ($form->generalErrors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
            <?php foreach ($form->fieldErrors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
