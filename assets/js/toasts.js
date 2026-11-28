/* Shared operation feedback; text is inserted safely and focus is never stolen. */
(function () {
  'use strict';
  const translate = window.MyParkLocale?.text ?? (message => message);

  const stack = document.getElementById('toast-stack');
  if (!stack) return;
  const polite = document.getElementById('toast-status');
  const urgent = document.getElementById('toast-alert');
  const active = new Map();
  const titles = { success: translate('Success'), info: translate('Information'), warning: translate('Reminder'), error: translate('Something went wrong') };
  const icons = { success: '✓', info: 'i', warning: '!', error: '!' };
  const durations = { success: 5000, info: 4000, warning: 10000, error: 10000 };

  function show(message, type = 'info', options = {}) {
    if (!Object.prototype.hasOwnProperty.call(titles, type)) type = 'info';
    const title = options.title || titles[type];
    const key = options.key || type + ':' + message;
    if (active.has(key)) return;
    const duration = options.parkingNotice ? 8000 : durations[type];
    const origin = document.activeElement;
    const toast = document.createElement('section');
    toast.className = 'pe-toast pe-toast--' + type;
    toast.style.setProperty('--toast-duration', duration + 'ms');
    if (options.parkingNotice) toast.classList.add('pe-toast--parking-notice');
    toast.setAttribute('aria-label', title);
    const icon = document.createElement('span');
    icon.className = 'pe-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = icons[type];
    const body = document.createElement('div');
    body.className = 'pe-toast__body';
    const heading = document.createElement('strong');
    heading.textContent = title;
    const text = document.createElement('p');
    text.textContent = translate(message);
    body.append(heading, text);
    if (options.details) {
      const link = document.createElement('a');
      link.href = 'receipts.php?type=parking#parking-sessions';
      link.textContent = translate('View parking details');
      body.append(link);
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'pe-toast__close';
    close.setAttribute('aria-label', 'Dismiss ' + title.toLowerCase() + ' notification');
    close.textContent = '×';
    toast.append(icon, body, close);
    stack.append(toast);
    active.set(key, toast);

    // Live regions already exist before their contents change.
    const announcer = type === 'error' ? urgent : polite;
    announcer.textContent = '';
    window.setTimeout(function () {
      if (toast.isConnected) announcer.textContent = title + '. ' + message;
    }, 50);

    let timer;
    let hovering = false;
    let remaining = duration;
    let started = null;
    function dismiss() {
      window.clearTimeout(timer);
      document.removeEventListener('visibilitychange', onVisibility);
      if (toast.contains(document.activeElement)) {
        if (origin && origin.isConnected && typeof origin.focus === 'function') origin.focus();
      }
      toast.remove();
      active.delete(key);
    }
    function resume() {
      if (!toast.isConnected || started !== null || hovering || toast.contains(document.activeElement) || document.hidden) return;
      started = performance.now();
      toast.setAttribute('data-counting-down', '');
      timer = window.setTimeout(dismiss, remaining);
    }
    function pause() {
      if (started === null) return;
      window.clearTimeout(timer);
      remaining = Math.max(0, remaining - (performance.now() - started));
      started = null;
      toast.removeAttribute('data-counting-down');
    }
    close.addEventListener('click', dismiss);
    toast.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); dismiss(); }
    });
    toast.addEventListener('mouseenter', function () { hovering = true; pause(); });
    toast.addEventListener('mouseleave', function () { hovering = false; resume(); });
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', function () { window.setTimeout(resume, 0); });
    // A tab switch should not consume the user's reading time.
    const onVisibility = function () {
      if (!toast.isConnected) { document.removeEventListener('visibilitychange', onVisibility); return; }
      if (document.hidden) pause(); else resume();
    };
    document.addEventListener('visibilitychange', onVisibility);
    resume();
  }
  window.MyParkToast = { show };

  // Keep server-rendered errors visible as a durable explanation and JS fallback.
  document.querySelectorAll('main .flash, main .error').forEach(function (element) {
    const message = element.textContent.trim();
    if (!message) return;
    const failure = element.classList.contains('error') || /not found|must be|enter a valid|use jpg|invalid|failed|cannot|can be cancelled|location is full/i.test(message);
    const success = /updated|created|reserved|cancelled|added|topped up|registered/i.test(message);
    const type = element.dataset.toastType || (failure ? 'error' : success ? 'success' : 'info');
    show(message, type, { title: element.dataset.toastTitle });
    if (type !== 'error') element.hidden = true;
  });
}());
