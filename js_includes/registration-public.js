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
    const stickyHome = document.querySelector('#sticky-home');

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

    const initStrength = () => {
        if (password === null || window.jQuery === undefined || typeof window.jQuery.fn.pwstrength !== 'function') return;
        window.jQuery(password).pwstrength({
            ui: {
                container: '#pwd-container',
                showErrors: true,
                useVerdictCssClass: true,
                showVerdictsInsideProgressBar: true,
                viewports: { progress: '.pwd-strength-viewport-progress' },
                progressBarExtraCssClasses: 'progress-bar-striped active',
                progressBarEmptyPercentage: 2,
                progressBarMinPercentage: 6,
            },
            common: {
                zxcvbn: true,
                minChar: 8,
                onKeyUp: (event, data) => {
                    document.querySelector('#length-help-text').textContent = `Length: ${event.target.value.length} | Score: ${data.score.toFixed(2)}`;
                },
            },
        });
    };

    const updateStickyHome = () => {
        if (stickyHome !== null) stickyHome.style.display = window.scrollY > 200 ? 'block' : 'none';
    };

    country?.addEventListener('change', setCountryFields);
    club?.addEventListener('change', setOtherClub);
    stickyHome?.addEventListener('click', (event) => {
        event.preventDefault();
        document.querySelector('#home')?.scrollIntoView({ behavior: 'smooth' });
    });
    window.addEventListener('scroll', updateStickyHome, { passive: true });
    setCountryFields();
    setOtherClub();
    initStrength();
    updateStickyHome();
})();
