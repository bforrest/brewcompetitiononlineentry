<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<fieldset>
    <legend>Entry logistics</legend>
    <div class="form-group">
        <label for="brewerDropOff" class="col-sm-3 control-label">Drop-off location</label>
        <div class="col-sm-6">
            <select class="form-control" id="brewerDropOff" name="brewerDropOff">
                <option value="">Select a drop-off location</option>
                <?php foreach ($options->dropOffChoices as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= ($form->values['brewerDropOff'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="help-block">Choose where you plan to deliver your entries.</span>
        </div>
    </div>
</fieldset>
