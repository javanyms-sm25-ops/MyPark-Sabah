/* Register validation: gentle first entry, inline errors after submission. */
(function () {
  'use strict';
  const form = document.getElementById('registration-form');
  if (!form) return;
  const fields = Object.fromEntries(['name', 'email', 'password', 'confirm'].map(key => [key, form.elements.namedItem(key)]));
  const summary = document.getElementById('registration-summary');
  const match = document.getElementById('register-password-match');
  const rule = document.getElementById('password-length-rule');
  const ruleStatus = document.getElementById('password-rule-status');
  const reported = new Set(Object.keys(fields).filter(key => fields[key].getAttribute('aria-invalid') === 'true'));
  const takenEmail = fields.email.dataset.emailTaken === 'true' ? fields.email.value.trim().toLowerCase() : null;

  function validate(key) {
    const value = fields[key].value;
    if (key === 'name') {
      if (!value.trim()) return 'Please enter your name.';
      if (Array.from(value.trim()).length < 2) return 'Name must be at least 2 characters.';
    }
    if (key === 'email') {
      if (!value.trim() || fields.email.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) return 'Enter an email address like name@example.com.';
      if (takenEmail === value.trim().toLowerCase()) return 'Email already registered.';
    }
    if (key === 'password' && Array.from(value).length < 8) return 'Password must be at least 8 characters.';
    if (key === 'confirm') {
      if (!value) return 'Please confirm your password.';
      if (value !== fields.password.value) return 'Passwords do not match.';
    }
    return '';
  }

  function showError(key) {
    const message = validate(key);
    const feedback = document.getElementById('register-' + key + '-error');
    fields[key].setAttribute('aria-invalid', message ? 'true' : 'false');
    feedback.querySelector('[data-error-text]').textContent = message;
    feedback.hidden = !message;
    if (!message) reported.delete(key);
    else reported.add(key);
    return !!message;
  }

  function updateHints() {
    const met = Array.from(fields.password.value).length >= 8;
    rule.classList.toggle('is-met', met);
    const status = met ? ' — met' : ' — not met';
    if (ruleStatus.textContent !== status) ruleStatus.textContent = status;
    const text = fields.confirm.value && fields.password.value === fields.confirm.value ? 'Passwords match.' : '';
    if (match.textContent !== text) match.textContent = text;
  }

  Object.keys(fields).forEach(function (key) {
    fields[key].addEventListener('input', function () {
      if (reported.has(key)) showError(key);
      if (key === 'password' && reported.has('confirm')) showError('confirm');
      if (key === 'password' || key === 'confirm') updateHints();
      if (!reported.size) summary.hidden = true;
    });
  });

  form.addEventListener('submit', function (event) {
    let count = 0;
    Object.keys(fields).forEach(key => { if (showError(key)) count++; });
    updateHints();
    summary.hidden = count === 0;
    if (count) {
      event.preventDefault();
      summary.textContent = 'Please check ' + count + (count === 1 ? ' highlighted field below.' : ' highlighted fields below.');
      summary.focus();
    }
  });
  updateHints();
}());
