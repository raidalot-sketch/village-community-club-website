'use strict';
// Browser hints complement validation and authorisation performed on the server.
document.querySelectorAll('input[name="password_confirmation"]').forEach(input => {
    const form = input.form;
    const source = form.querySelector('input[name="new_password"], input[name="password"]');
    if (!source) return;
    const validate = () => input.setCustomValidity(input.value !== source.value ? 'The passwords must match.' : '');
    input.addEventListener('input', validate);
    source.addEventListener('input', validate);
});
