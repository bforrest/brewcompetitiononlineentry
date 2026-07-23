<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<?php if (($options->availability['judge'] ?? false) || ($options->availability['steward'] ?? false)): ?>
    <fieldset>
        <legend>Volunteer preferences</legend>
        <?php if ($options->availability['judge'] ?? false): ?>
            <div class="form-group">
                <span class="col-sm-3 control-label">Would you like to judge?</span>
                <div class="col-sm-6">
                    <label class="radio-inline"><input type="radio" name="brewerJudge" value="Y"<?= ($form->values['brewerJudge'] ?? 'N') === 'Y' ? ' checked' : '' ?>> Yes</label>
                    <label class="radio-inline"><input type="radio" name="brewerJudge" value="N"<?= ($form->values['brewerJudge'] ?? 'N') !== 'Y' ? ' checked' : '' ?>> No</label>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($options->availability['steward'] ?? false): ?>
            <div class="form-group">
                <span class="col-sm-3 control-label">Would you like to steward?</span>
                <div class="col-sm-6">
                    <label class="radio-inline"><input type="radio" name="brewerSteward" value="Y"<?= ($form->values['brewerSteward'] ?? 'N') === 'Y' ? ' checked' : '' ?>> Yes</label>
                    <label class="radio-inline"><input type="radio" name="brewerSteward" value="N"<?= ($form->values['brewerSteward'] ?? 'N') !== 'Y' ? ' checked' : '' ?>> No</label>
                </div>
            </div>
        <?php endif; ?>
    </fieldset>
<?php endif; ?>
