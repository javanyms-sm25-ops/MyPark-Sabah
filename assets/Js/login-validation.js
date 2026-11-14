/* Login errors stay beside their fields; authentication remains on the server. */
(function () {
  'use strict';

  const form = document.getElementById('login-form');
  if (!form) return;

  const fields = ['email', 'password'].map(function (name) {
    return form.elements.namedItem(name);
  });

  function validate(field) {
    if (field.name === 'password') {
      return field.value === '' ? 'Please enter your password.' : '';
    }
    if (!field.value.trim()) return 'Please enter your email address.';
    if (field.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
      return 'Please enter a valid email address.';
    }
    return '';
  }

  function showError(field) {
    const message = validate(field);
    const feedback = document.getElementById('login-' + field.name + '-error');
    field.setAttribute('aria-invalid', message ? 'true' : 'false');
    feedback.querySelector('[data-error-text]').textContent = message;
    feedback.hidden = !message;
    return Boolean(message);
  }

  fields.forEach(function (field) {
    field.addEventListener('input', function () {
      if (field.getAttribute('aria-invalid') === 'true') showError(field);
    });
  });

  form.addEventListener('submit', function (event) {
    let firstInvalid = null;
    fields.forEach(function (field) {
      if (showError(field) && !firstInvalid) firstInvalid = field;
    });
    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.focus();
    }
  });
}());
