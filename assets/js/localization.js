/* Translate interface copy without changing form values or stored records. */
(function () {
  'use strict';

  const source = document.getElementById('mypark-translations');
  const messages = source ? JSON.parse(source.textContent) : {};

  function text(message, params = {}) {
    const key = message.toLowerCase();
    const template = Object.prototype.hasOwnProperty.call(messages, key) ? messages[key] : message;
    return template.replace(/\{(\w+)\}/g, (match, name) =>
      Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match
    );
  }

  window.MyParkLocale = { text };
  document.querySelectorAll('[data-language-switcher]').forEach(form => {
    form.elements.namedItem('lang').addEventListener('change', () => form.requestSubmit());
  });
}());
