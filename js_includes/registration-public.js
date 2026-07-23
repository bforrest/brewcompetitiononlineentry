(() => {
    'use strict';

    const form = document.querySelector('#register-form');
    if (form === null) {
        return;
    }

    const email = form.querySelector('#user_name');
    const confirmation = form.querySelector('#user_name2');

    const messageFor = (field) => {
        if (field === confirmation && email !== null && confirmation.value !== '' && confirmation.value !== email.value) {
            return 'Email addresses must match.';
        }

        if (!field.validity.valid) {
            return field.validity.valueMissing ? 'This field is required.' : 'Please provide a valid value.';
        }

        return '';
    };

    const renderField = (field) => {
        const group = field.closest('.form-group');
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
            if (field === email && confirmation !== null && confirmation.value !== '') {
                renderField(confirmation);
            }
            if (field.value !== '') {
                renderField(field);
            }
        });
        field.addEventListener('change', () => renderField(field));
    }
})();
