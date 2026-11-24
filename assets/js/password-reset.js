(() => {
  'use strict';

  const codeForm = document.getElementById('reset-code-form');
  if (!codeForm) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const control = codeForm.querySelector('[data-reset-code]');
  const code = codeForm.elements.namedItem('code');
  const slots = [...control.querySelectorAll('.reset-code-slots > span')];
  const verify = document.getElementById('verify-reset-code');
  const resendForm = document.getElementById('reset-resend-form');
  const resend = resendForm.querySelector('button[type="submit"]');
  const countdown = resendForm.querySelector('[data-reset-countdown]');
  const resendDeadline = Date.now() + Number(resendForm.dataset.resendSeconds) * 1000;
  let resendTimer;

  const digits = value => value.normalize('NFKC').replace(/[^0-9]/g, '').slice(0, slots.length);

  function renderCode() {
    const value = digits(code.value);
    if (code.value !== value) code.value = value;
    const current = Math.min(code.selectionStart ?? value.length, slots.length - 1);
    slots.forEach((slot, index) => {
      slot.textContent = value[index] ?? '';
      slot.classList.toggle('is-current', index === current);
    });
    verify.disabled = value.length !== slots.length || codeForm.hasAttribute('aria-busy');
  }

  function renderResend() {
    window.clearTimeout(resendTimer);
    const seconds = Math.max(0, Math.ceil((resendDeadline - Date.now()) / 1000));
    resend.hidden = seconds > 0;
    resend.disabled = seconds > 0 || resendForm.hasAttribute('aria-busy');
    countdown.hidden = seconds === 0;
    if (seconds > 0) {
      const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');
      const remainder = String(seconds % 60).padStart(2, '0');
      countdown.textContent = translate('Resend code in {time}').replace('{time}', minutes + ':' + remainder);
      resendTimer = window.setTimeout(renderResend, 1000);
    }
  }

  for (const event of ['input', 'focus', 'keyup', 'click', 'select']) code.addEventListener(event, renderCode);
  code.addEventListener('paste', event => {
    const value = digits(event.clipboardData?.getData('text') ?? '');
    if (value.length !== slots.length) return;
    event.preventDefault();
    code.value = value;
    renderCode();
  });

  const actions = [[codeForm, verify], [resendForm, resend]];
  for (const [form, button] of actions) {
    form.addEventListener('submit', event => {
      if (button.disabled) event.preventDefault();
      if (event.defaultPrevented) return;
      form.setAttribute('aria-busy', 'true');
      button.disabled = true;
    });
  }
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    for (const [form] of actions) form.removeAttribute('aria-busy');
    renderCode();
    renderResend();
  });

  control.classList.add('is-enhanced');
  renderCode();
  renderResend();
})();
