(() => {
  'use strict';

  const methods = document.getElementById('checkout-methods');
  if (!methods) return;

  const panels = document.querySelectorAll('[data-payment-panel]');
  const bank = document.getElementById('checkout-fpx-bank');
  const status = document.getElementById('checkout-simulation-status');
  const qr = document.getElementById('checkout-demo-qr');
  const cardForm = document.getElementById('checkout-demo-card');
  const cardSubmit = document.getElementById('checkout-card-submit');
  const cardFields = Array.from(cardForm.querySelectorAll('textarea'), field => ({
    field,
    format: field.dataset.format ? new RegExp(`^(?:${field.dataset.format})$`) : null,
  }));
  const fpxButton = document.querySelector('[data-payment-panel="fpx"] [data-simulate-payment]');

  function selectMethod() {
    const selected = methods.querySelector('input:checked').value;
    for (const panel of panels) panel.hidden = panel.dataset.paymentPanel !== selected;
    status.textContent = '';
    if (selected === 'qr' && !qr.hasChildNodes() && window.QRCode) {
      new window.QRCode(qr, {
        text: qr.dataset.qrText,
        width: 160,
        height: 160,
        colorDark: '#d41453',
        colorLight: '#ffffff',
      });
    }
  }

  function updateBank() {
    fpxButton.disabled = bank.value === '';
    status.textContent = '';
  }

  methods.addEventListener('change', selectMethod);
  bank.addEventListener('change', updateBank);
  cardForm.addEventListener('submit', event => {
    event.preventDefault();
    for (const { field, format } of cardFields) {
      field.value = field.value.trim();
      field.setCustomValidity(field.value && format && !format.test(field.value) ? field.dataset.invalid : '');
    }
    if (!cardForm.reportValidity()) return;
    cardForm.reset();
    status.textContent = status.dataset.complete;
  });
  cardForm.addEventListener('input', ({ target: field }) => {
    if (field.value.includes('\n')) field.value = field.value.replace(/\n/g, ' ');
    field.setCustomValidity('');
    status.textContent = '';
  });
  cardForm.addEventListener('keydown', event => {
    if (event.key !== 'Enter' || event.isComposing || !event.target.matches('textarea')) return;
    event.preventDefault();
    cardForm.requestSubmit(cardSubmit);
  });
  cardSubmit.disabled = false;
  for (const button of document.querySelectorAll('[data-simulate-payment]')) {
    button.addEventListener('click', () => {
      status.textContent = status.dataset.complete;
    });
  }
  updateBank();
  selectMethod();
})();
