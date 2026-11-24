/* UTC arithmetic keeps the dates unchanged across browser time zones. */
(() => {
  'use strict';

  const dialog = document.getElementById('date-range-picker');
  const template = document.getElementById('date-range-trigger-template');
  if (!template || typeof dialog?.showModal !== 'function') return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const days = dialog.querySelector('[data-calendar-days]');
  const weekdays = dialog.querySelector('[data-calendar-week]');
  const monthLabel = dialog.querySelector('#date-range-month');
  const previous = dialog.querySelector('[data-calendar-prev]');
  const next = dialog.querySelector('[data-calendar-next]');
  const selection = dialog.querySelector('[data-range-selection]');
  const instruction = dialog.querySelector('[data-range-instruction]');
  const apply = dialog.querySelector('[data-range-apply]');
  const dayLength = 86400000;
  function parseDay(value) {
    const day = Date.parse(value + 'T00:00:00Z');
    return Number.isFinite(day) ? day : null;
  }
  const isoDay = day => day === null ? '' : new Date(day).toISOString().slice(0, 10);
  const today = parseDay(dialog.dataset.today);
  const locale = document.documentElement.lang || 'en-MY';
  const formatter = options => new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' });
  const dateLabel = formatter({ day: 'numeric', month: 'short', year: 'numeric' });
  const fullDateLabel = formatter({ dateStyle: 'full' });
  const monthName = formatter({ month: 'long', year: 'numeric' });
  const weekdayName = formatter({ weekday: 'short' });
  let view;
  let opener;
  let start;
  let end;
  let choosingEnd;
  let displayedMonth;

  function monthStart(day, offset = 0) {
    const date = new Date(day);
    date.setUTCDate(1);
    date.setUTCMonth(date.getUTCMonth() + offset);
    return date.getTime();
  }

  function renderMonth(focusDay, moveFocus = false) {
    displayedMonth = monthStart(focusDay);
    const followingMonth = monthStart(displayedMonth, 1);
    monthLabel.textContent = monthName.format(displayedMonth);
    previous.disabled = displayedMonth <= monthStart(view.earliest);
    next.disabled = followingMonth > view.latest;
    const cells = document.createDocumentFragment();
    const leadingBlanks = (new Date(displayedMonth).getUTCDay() + 6) % 7;
    for (let index = 0; index < leadingBlanks; index++) cells.append(document.createElement('span'));

    const first = start ?? end;
    const last = end ?? start;
    for (let day = displayedMonth; day < followingMonth; day += dayLength) {
      const button = document.createElement('button');
      const number = document.createElement('span');
      const inRange = first !== null && day >= first && day <= last;
      button.type = 'button';
      button.className = 'date-range-day';
      button.dataset.day = day;
      button.disabled = day < view.earliest || day > view.latest;
      button.tabIndex = day === focusDay ? 0 : -1;
      button.setAttribute('aria-label', fullDateLabel.format(day));
      button.setAttribute('aria-pressed', String(inRange));
      button.classList.toggle('is-in-range', inRange);
      button.classList.toggle('is-start', day === first);
      button.classList.toggle('is-end', day === last);
      number.textContent = new Date(day).getUTCDate();
      button.append(number);
      cells.append(button);
    }
    days.replaceChildren(cells);
    if (moveFocus) days.querySelector('[tabindex="0"]')?.focus();
  }

  function updateSelection(message) {
    const dates = [...new Set([start, end])].filter(day => day !== null);
    selection.hidden = dates.length === 0;
    selection.textContent = dates.map(day => dateLabel.format(day)).join(' – ');
    instruction.textContent = translate(message);
    apply.disabled = (view.startInput.required && start === null) || (view.endInput.required && end === null);
  }

  function openCalendar(config, trigger, field) {
    if (dialog.open) return;
    view = config;
    opener = trigger;
    start = parseDay(config.startInput.value);
    end = parseDay(config.endInput.value);
    choosingEnd = field === 'end';
    const preferred = (choosingEnd ? end ?? start : start ?? end) ?? today;
    updateSelection(choosingEnd ? 'Choose an end date.' : 'Choose a start date.');
    dialog.showModal();
    renderMonth(Math.max(config.earliest, Math.min(preferred, config.latest)), true);
  }

  function enhance(startInput, endInput, onApply) {
    const config = {
      startInput,
      endInput,
      onApply,
      earliest: parseDay(startInput.min) ?? parseDay('0001-01-01'),
      latest: parseDay(endInput.max) ?? parseDay('9999-12-31')
    };
    const controls = [[startInput, 'start'], [endInput, 'end']].map(([input, field]) => {
      const label = input.labels[0];
      const trigger = template.content.firstElementChild.cloneNode(true);
      const labelText = label.textContent.trim();
      trigger.id = input.id + '-trigger';
      const description = input.getAttribute('aria-describedby');
      if (description) trigger.setAttribute('aria-describedby', description);
      label.htmlFor = trigger.id;
      input.after(trigger);
      input.hidden = true;
      trigger.addEventListener('click', () => openCalendar(config, trigger, field));
      input.addEventListener('invalid', event => {
        event.preventDefault();
        openCalendar(config, trigger, field);
      });
      return { input, trigger, labelText };
    });
    config.sync = () => {
      for (const { input, trigger, labelText } of controls) {
        const day = parseDay(input.value);
        const text = day === null ? translate('Select a date') : dateLabel.format(day);
        trigger.querySelector('span').textContent = text;
        trigger.setAttribute('aria-label', labelText + ': ' + text);
      }
    };
    config.sync();
    return config.sync;
  }

  for (let index = 0; index < 7; index++) {
    const weekday = document.createElement('span');
    weekday.textContent = weekdayName.format(Date.UTC(2024, 0, 1 + index));
    weekdays.append(weekday);
  }

  days.addEventListener('click', event => {
    const button = event.target.closest('button[data-day]');
    if (!button || button.disabled) return;
    const day = Number(button.dataset.day);
    if (choosingEnd) {
      end = start === null ? day : Math.max(day, start);
      if (start !== null) start = Math.min(day, start);
      choosingEnd = false;
    } else if (start === null && end !== null) {
      start = Math.min(day, end);
      end = Math.max(day, end);
    } else {
      start = day;
      end = null;
      choosingEnd = true;
    }
    updateSelection(choosingEnd ? 'Choose an end date.' : (start === null ? 'Choose a start date.' : 'Date range selected.'));
    renderMonth(day, true);
  });

  const arrowSteps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
  days.addEventListener('keydown', event => {
    const button = event.target.closest('button[data-day]');
    const step = arrowSteps[event.key];
    if (!button || typeof step !== 'number') return;
    event.preventDefault();
    const day = Number(button.dataset.day) + step * dayLength;
    if (day >= view.earliest && day <= view.latest) renderMonth(day, true);
  });

  [[previous, -1], [next, 1]].forEach(([control, offset]) => {
    control.addEventListener('click', () => {
      const day = Math.max(view.earliest, monthStart(displayedMonth, offset));
      renderMonth(day);
      if (control.disabled) days.querySelector('[tabindex="0"]')?.focus();
    });
  });

  function closeCalendar() {
    dialog.close();
    opener?.focus();
  }
  dialog.querySelector('[data-range-cancel]').addEventListener('click', closeCalendar);
  dialog.addEventListener('cancel', event => {
    event.preventDefault();
    closeCalendar();
  });
  apply.addEventListener('click', () => {
    view.startInput.value = isoDay(start);
    view.endInput.value = isoDay(end);
    view.sync();
    closeCalendar();
    view.onApply?.();
  });

  window.MyParkDateRange = { enhance };
  document.querySelectorAll('form[data-date-range]').forEach(form => {
    const submit = form.querySelector('[data-date-range-submit]');
    enhance(
      form.querySelector('[data-date-range-start]'),
      form.querySelector('[data-date-range-end]'),
      submit ? () => form.requestSubmit(submit) : undefined
    );
    if (submit) submit.hidden = true;
    form.classList.add('has-date-picker');
  });
})();
