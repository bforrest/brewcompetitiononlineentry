<?php
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormData $form */
/** @var \Bcoem\Domain\Registration\Form\RegistrationFormOptions $options */
?>
<fieldset>
    <legend>Contact and address</legend>
    <?php foreach (['brewerFirstName' => 'First name', 'brewerLastName' => 'Last name', 'brewerAddress' => 'Address', 'brewerCity' => 'City', 'brewerZip' => 'Postal code', 'brewerPhone1' => 'Phone'] as $name => $label): ?>
        <div class="form-group<?= isset($form->fieldErrors[$name]) ? ' has-error' : '' ?>">
            <label for="<?= e($name) ?>" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> <?= e($label) ?></label>
            <div class="col-sm-6">
                <input class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= $name === 'brewerPhone1' ? 'tel' : 'text' ?>" value="<?= e((string) ($form->values[$name] ?? '')) ?>" required>
                <?php if (isset($form->fieldErrors[$name])): ?><span class="help-block"><?= e($form->fieldErrors[$name]) ?></span><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="form-group<?= isset($form->fieldErrors['brewerCountry']) ? ' has-error' : '' ?>">
        <label for="brewerCountry" class="col-sm-3 control-label text-warning"><span aria-hidden="true">*</span> Country</label>
        <div class="col-sm-6">
            <select class="form-control" id="brewerCountry" name="brewerCountry" required>
                <option value="">Select a country</option>
                <?php foreach ($options->countryChoices as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= ($form->values['brewerCountry'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($form->fieldErrors['brewerCountry'])): ?><span class="help-block"><?= e($form->fieldErrors['brewerCountry']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="form-group">
        <label for="brewerStateUS" class="col-sm-3 control-label">State or province</label>
        <div class="col-sm-6">
            <select class="form-control" id="brewerStateUS" name="brewerStateUS">
                <option value="">Select a state or province</option>
                <?php foreach ($options->stateChoices as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= ($form->values['brewerStateUS'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</fieldset>
