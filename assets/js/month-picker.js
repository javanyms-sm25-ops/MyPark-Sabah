(() => {
  'use strict';

  const dialog = document.getElementById('month-picker');
  if (typeof dialog?.showModal !== 'function') return;

  const yearInput = dialog.querySelector('#month-picker-year');
  const previousYear = dialog.querySelector('[data-month-previous-year]');
  const nextYear = dialog.querySelector('[data-month-next-year]');
  const choices = dialog.querySelector('[data-month-choices]');
  const current = dialog.querySelector('[data-month-current]');
  const apply = dialog.querySelector('[data-month-apply]');
  const locale = document.documentElement.lang || 'en-MY';
  const format = options => new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' });
  const monthAndYear = format({ month: 'long', year: 'numeric' });
  const shortMonth = format({ month: 'short' });
  const longMonth = format({ month: 'long' });
  const parse = value => value.split('-').map(Number);
  const isAllowed = value => value >= input.min && value <= input.max;
  const monthValue = selectedMonth => String(yearInput.valueAsNumber).padStart(4, '0') + '-' + String(selectedMonth).padStart(2, '0');
  let month, input, label, trigger;

  function syncLabel() {
    const [year, selectedMonth] = parse(input.value || dialog.dataset.currentMonth);
    trigger.textContent = monthAndYear.format(Date.UTC(year, selectedMonth - 1, 1));
    trigger.setAttribute('aria-label', label.textContent.trim() + ': ' + trigger.textContent);
  }

  const buttons = Array.from({ length: 12 }, (_, i) => {
    const button = document.createElement('button');
    const date = Date.UTC(2000, i, 1);
    button.type = 'button';
    button.textContent = shortMonth.format(date);
    button.setAttribute('aria-label', longMonth.format(date));
    button.addEventListener('click', () => {
      month = i + 1;
      render();
    });
    return button;
  });
  choices.append(...buttons);

  function render() {
    const validYear = yearInput.validity.valid;
    previousYear.disabled = !validYear || yearInput.valueAsNumber <= Number(yearInput.min);
    nextYear.disabled = !validYear || yearInput.valueAsNumber >= Number(yearInput.max);
    buttons.forEach((button, i) => {
      button.disabled = !validYear || !isAllowed(monthValue(i + 1));
      if (!button.disabled && i + 1 === month) button.setAttribute('aria-current', 'true');
      else button.removeAttribute('aria-current');
    });
    apply.disabled = !validYear || !isAllowed(monthValue(month));
  }

  function showMonth(value) {
    const [year, selectedMonth] = parse(value);
    yearInput.value = year;
    month = selectedMonth;
    render();
  }

  function openPicker() {
    showMonth(input.value || dialog.dataset.currentMonth);
    dialog.showModal();
    buttons[month - 1].focus();
  }
  yearInput.addEventListener('input', render);
  [[previousYear, -1], [nextYear, 1]].forEach(([button, step]) => {
    button.addEventListener('click', () => {
      yearInput.value = yearInput.valueAsNumber + step;
      render();
      if (button.disabled) yearInput.focus();
    });
  });
  current.addEventListener('click', () => showMonth(dialog.dataset.currentMonth));
  dialog.querySelector('[data-month-cancel]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { if (trigger?.isConnected) trigger.focus(); });
  apply.addEventListener('click', () => {
    input.value = monthValue(month);
    syncLabel();
    input.dispatchEvent(new Event('change', { bubbles: true }));
    dialog.close();
  });

  function enhanceMonthField() {
    input = document.querySelector('[data-month-picker-input]');
    if (!input || input.hidden) return;
    if (dialog.open) dialog.close();
    label = input.labels[0];
    yearInput.min = parse(input.min)[0];
    yearInput.max = parse(input.max)[0];
    current.disabled = !isAllowed(dialog.dataset.currentMonth);
    trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.id = input.id + '-trigger';
    trigger.className = 'date-range-trigger month-picker-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', dialog.id);
    trigger.addEventListener('click', openPicker);
    syncLabel();
    input.after(trigger);
    label.htmlFor = trigger.id;
    input.hidden = true;
  }

  enhanceMonthField();
  document.addEventListener('mypark:monthly-attendance-updated', enhanceMonthField);
})();
