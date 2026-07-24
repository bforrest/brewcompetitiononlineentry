<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<fieldset>
    <legend>Waiver</legend>
    <div class="mb-3 row">
        <div class="offset-sm-3 offset-lg-2 col-xs-12 col-sm-9 col-lg-10">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="brewerJudgeWaiver" value="Y" id="brewerJudgeWaiver"<?= ($form->values['brewerJudgeWaiver'] ?? 'Y') === 'Y' ? ' checked' : '' ?> required>
                <label class="form-check-label" for="brewerJudgeWaiver">I agree to the competition waiver.</label>
            </div>
            <div class="help-block">You must agree to the waiver to register.</div>
        </div>
    </div>
</fieldset>
