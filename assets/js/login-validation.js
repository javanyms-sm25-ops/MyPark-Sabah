/* Login errors stay beside their fields; authentication remains on the server. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);


  const form = document.getElementById('login-form');
  if (!form) return;

  const fields = ['email', 'password'].map(function (name) {
    return form.elements.namedItem(name);
  });
  const submitButton = form.querySelector('.login-submit');
  const submitLabel = form.querySelector('[data-submit-label]');
  const spinner = form.querySelector('.login-spinner');
  const requestError = document.getElementById('login-request-error');
  const status = document.getElementById('login-status');
  let submitting = false;

  async function requestRecaptchaToken() {
    const siteKey = form.dataset.recaptchaSiteKey;
    if (!siteKey) throw new Error('Missing verification configuration');

    let timeout;
    try {
      return await Promise.race([
        new Promise((resolve, reject) => {
          const captcha = window.grecaptcha;
          if (!captcha || typeof captcha.ready !== 'function') {
            reject(new Error('Verification script not ready'));
            return;
          }
          captcha.ready(() => {
            Promise.resolve()
              .then(() => captcha.execute(siteKey, { action: 'login' }))
              .then(resolve, reject);
          });
        }),
        new Promise((_, reject) => {
          timeout = window.setTimeout(() => reject(new Error('Verification timed out')), 10000);
        })
      ]);
    } finally {
      window.clearTimeout(timeout);
    }
  }

  function setSubmitting(value) {
    submitting = value;
    submitButton.disabled = value;
    spinner.hidden = !value;
    submitLabel.textContent = value ? translate('Signing in…') : translate('Sign in');
    status.textContent = value ? translate('Signing in…') : '';
    form.setAttribute('aria-busy', String(value));
    fields.forEach(function (field) { field.readOnly = value; });
  }

  function showRequestError(message) {
    requestError.querySelector('[data-request-error]').textContent = message;
    requestError.hidden = false;
    requestError.focus();
  }

  function validate(field) {
    if (field.name === 'password') {
      return field.value === '' ? translate('Please enter your password.') : '';
    }
    if (!field.value.trim()) return translate('Please enter your email address.');
    if (field.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
      return translate('Please enter a valid email address.');
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

  form.addEventListener('submit', async function (event) {
    if (submitting) { event.preventDefault(); return; }
    let firstInvalid = null;
    fields.forEach(function (field) {
      if (showError(field) && !firstInvalid) firstInvalid = field;
    });
    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.focus();
      return;
    }

    // Post the same fields to the existing PHP handler; no new auth endpoint.
    // Older browsers retain the normal full-page submission.
    if (!window.fetch || !window.DOMParser) return;
    event.preventDefault();
    requestError.hidden = true;
    const payload = new FormData(form);
    setSubmitting(true);
    try {
      try {
        const token = await requestRecaptchaToken();
        if (typeof token !== 'string' || token.trim() === '') throw new Error('Missing token');
        payload.set('g-recaptcha-response', token);
      } catch (_) {
        showRequestError(translate('Security verification is unavailable. Please try signing in again.'));
        return;
      }
      let response;
      try {
        response = await window.fetch(form.action, {
          method: 'POST', body: payload, credentials: 'same-origin'
        });
      } catch (_) {
        showRequestError('Unable to connect. Check your connection and try again.');
        return;
      }
      if (!response.ok) {
        showRequestError('Sign in is temporarily unavailable. Please refresh the page and try again.');
        return;
      }
      if (response.redirected) {
        const target = new URL(response.url);
        if (target.origin === window.location.origin) {
          window.location.assign(target.href);
          return;
        }
        showRequestError('Unable to finish signing in. Please refresh the page and try again.');
        return;
      }
      let html;
      try { html = await response.text(); }
      catch (_) {
        showRequestError('The connection was interrupted. Please refresh the page before trying again.');
        return;
      }
      const page = new DOMParser().parseFromString(html, 'text/html');
      const serverForm = page.getElementById('login-form');
      if (!serverForm) {
        showRequestError('Unable to finish signing in. Please refresh the page and try again.');
        return;
      }
      // Preserve server validation and lockout messages; never infer auth failure
      // from an HTTP/network error. Do not execute scripts from the response.
      const csrf = serverForm.querySelector('input[name="csrf"]');
      if (csrf) form.elements.namedItem('csrf').value = csrf.value;
      let firstServerError = null;
      fields.forEach(function (field) {
        const serverField = serverForm.elements.namedItem(field.name);
        const feedback = document.getElementById('login-' + field.name + '-error');
        const serverFeedback = page.getElementById(feedback.id);
        const invalid = serverField && serverField.getAttribute('aria-invalid') === 'true';
        field.setAttribute('aria-invalid', String(Boolean(invalid)));
        feedback.hidden = !invalid;
        feedback.querySelector('[data-error-text]').textContent = invalid && serverFeedback
          ? serverFeedback.querySelector('[data-error-text]').textContent : '';
        if (invalid && !firstServerError) firstServerError = field;
      });
      const serverError = page.getElementById('login-request-error');
      const message = serverError && !serverError.hidden
        ? serverError.querySelector('[data-request-error]').textContent.trim() : '';
      if (message) showRequestError(message);
      else if (firstServerError) firstServerError.focus();
      else showRequestError('Unable to finish signing in. Please refresh the page and try again.');
    } catch (_) {
      showRequestError('Unable to finish signing in. Please refresh the page and try again.');
    } finally {
      setSubmitting(false);
    }
  });

  window.addEventListener('pageshow', function () { setSubmitting(false); });
  const initialError = fields.find(function (field) { return field.getAttribute('aria-invalid') === 'true'; });
  if (initialError) initialError.focus();
  else if (!requestError.hidden) requestError.focus();
}());
