/* Shared operation feedback; text is inserted safely and focus is never stolen. */
(function () {
  'use strict';
  const stack = document.getElementById('toast-stack');
  if (!stack) return;
  const polite = document.getElementById('toast-status');
  const urgent = document.getElementById('toast-alert');
  const active = new Map();
  const titles = { success: 'Success', info: 'Information', warning: 'Reminder', error: 'Something went wrong' };
  const icons = { success: '✓', info: 'i', warning: '!', error: '!' };

  function show(message, type = 'info', options = {}) {
    if (!Object.prototype.hasOwnProperty.call(titles, type)) type = 'info';
    const key = options.key || type + ':' + message;
    if (active.has(key)) return;
    const origin = document.activeElement;
    const toast = document.createElement('section');
    toast.className = 'pe-toast pe-toast--' + type;
    toast.setAttribute('aria-label', titles[type]);
    const icon = document.createElement('span');
    icon.className = 'pe-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = icons[type];
    const body = document.createElement('div');
    body.className = 'pe-toast__body';
    const heading = document.createElement('strong');
    heading.textContent = titles[type];
    const text = document.createElement('p');
    text.textContent = message;
    body.append(heading, text);
    if (options.details) {
      const link = document.createElement('a');
      link.href = 'history.php';
      link.textContent = 'View parking details';
      body.append(link);
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'pe-toast__close';
    close.setAttribute('aria-label', 'Dismiss ' + titles[type].toLowerCase() + ' notification');
    close.textContent = '×';
    toast.append(icon, body, close);
    stack.append(toast);
    active.set(key, toast);

    // Live regions already exist before their contents change.
    const announcer = type === 'error' ? urgent : polite;
    announcer.textContent = '';
    window.setTimeout(function () {
      if (toast.isConnected) announcer.textContent = titles[type] + '. ' + message;
    }, 50);

    let timer;
    let hovering = false;
    let remaining = type === 'success' ? 5000 : 4000;
    let started;
    const autoClose = type === 'success' || type === 'info';
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
      if (!autoClose || hovering || toast.contains(document.activeElement) || document.hidden) return;
      window.clearTimeout(timer);
      started = Date.now();
      timer = window.setTimeout(dismiss, Math.max(remaining, 250));
    }
    function pause() {
      window.clearTimeout(timer);
      if (started) remaining = Math.max(0, remaining - (Date.now() - started));
      started = null;
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
  window.ParkEasyToast = { show };

  // Keep server-rendered errors visible as a durable explanation and JS fallback.
  document.querySelectorAll('main .flash, main .error').forEach(function (element) {
    const message = element.textContent.trim();
    if (!message) return;
    const failure = element.classList.contains('error') || /not found|must be|enter a valid|use jpg|invalid|failed|cannot/i.test(message);
    const success = /updated|created|reserved|cancelled|added|topped up|registered/i.test(message);
    const type = failure ? 'error' : success ? 'success' : 'info';
    show(message, type);
    if (type !== 'error') element.hidden = true;
  });
}());
