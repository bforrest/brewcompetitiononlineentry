(() => {
    'use strict';

    const form = document.querySelector('#submit-form');
    if (form === null) {
        return;
    }

    const password = form.querySelector('#password-entry');
    const confirmation = form.querySelector('#password-confirm');
    const country = form.querySelector('#brewerCountry');
    const addressFields = form.querySelector('#address-fields');
    const club = form.querySelector('#brewerClubs');
    const otherClub = form.querySelector('#brewerClubsOther');
    const strength = form.querySelector('.pwd-strength-viewport-progress');

    const messageFor = (field) => {
        if (field === confirmation && password !== null && confirmation.value !== '' && confirmation.value !== password.value) {
            return 'Passwords do not match.';
        }

        if (!field.validity.valid) {
            return field.validity.valueMissing ? 'This field is required.' : 'Please provide a valid value.';
        }

        return '';
    };

    const renderField = (field) => {
        const group = field.closest('.row, .form-group');
        if (group === null) {
            return true;
        }

        const errorId = `${field.id}-client-error`;
        const existing = document.getElementById(errorId);
        const message = messageFor(field);

        if (message === '') {
            field.removeAttribute('aria-invalid');
            existing?.remove();
            if (group.querySelector('.help-block') === null) {
                group.classList.remove('has-error');
            }
            return true;
        }

        field.setAttribute('aria-invalid', 'true');
        group.classList.add('has-error');
        const error = existing ?? document.createElement('span');
        error.id = errorId;
        error.className = 'help-block';
        error.textContent = message;
        if (existing === null) {
            field.parentElement?.append(error);
        }

        return false;
    };

    const fields = [...form.querySelectorAll('input[required], select[required], textarea[required]')];
    const validate = () => fields.map(renderField).every(Boolean);

    form.addEventListener('submit', (event) => {
        if (!validate()) {
            event.preventDefault();
            form.querySelector('[aria-invalid="true"]')?.focus();
        }
    });

    for (const field of fields) {
        field.addEventListener('input', () => {
            if (field === password && confirmation !== null && confirmation.value !== '') {
                renderField(confirmation);
            }
            if (field.value !== '') {
                renderField(field);
            }
        });
        field.addEventListener('change', () => renderField(field));
    }

    const setCountryFields = () => {
        if (country === null) return;
        const selected = country.value;
        if (addressFields !== null) addressFields.hidden = selected === '';
        const mapping = { 'United States': 'us', Australia: 'aus', Canada: 'ca' };
        const active = mapping[selected] ?? 'non-us';
        for (const id of ['us', 'aus', 'ca', 'non-us']) {
            const container = form.querySelector(`#${id}-state`);
            const field = container?.querySelector('input, select');
            if (container !== null) container.hidden = id !== active;
            if (field !== null) field.required = id === active;
        }
    };

    const setOtherClub = () => {
        if (club !== null && otherClub !== null) otherClub.hidden = club.value !== 'Other';
    };

    const renderStrength = () => {
        if (password === null || strength === null) return;
        const score = Math.min(100, password.value.length * 10);
        strength.innerHTML = `<div class="progress"><div class="progress-bar" role="progressbar" style="width: ${score}%"></div></div>`;
    };

    country?.addEventListener('change', setCountryFields);
    club?.addEventListener('change', setOtherClub);
    password?.addEventListener('input', renderStrength);
    setCountryFields();
    setOtherClub();
    renderStrength();
})();
