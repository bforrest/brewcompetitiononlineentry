<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<fieldset>
    <legend>Waiver</legend>
    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-6">
            <div class="checkbox">
                <label><input type="checkbox" name="brewerJudgeWaiver" value="Y"<?= ($form->values['brewerJudgeWaiver'] ?? 'Y') === 'Y' ? ' checked' : '' ?> required> I agree to the competition waiver.</label>
            </div>
            <span class="help-block">You must agree to the waiver to register.</span>
        </div>
    </div>
</fieldset>
