(() => {
  'use strict';

  const selector = '[data-monthly-attendance]';
  let panel = document.querySelector(selector);
  if (!panel || new URLSearchParams(location.search).has('shift') || !window.MyParkTableRequest) return;

  const translate = window.MyParkLocale?.text ?? (message => message);
  const request = window.MyParkTableRequest.create();
  const header = 'X-MyPark-Monthly-Attendance';
  const loadError = 'Could not update monthly attendance. Please try again.';

  function setBusy(busy, message = '') {
    panel.setAttribute('aria-busy', String(busy));
    panel.querySelector('[data-monthly-status]').textContent = translate(busy ? 'Loading monthly attendance…' : message);
  }

  function load(url, addHistory) {
    const notice = panel.querySelector('[data-monthly-error]');
    notice.hidden = true;
    request.run(async signal => {
      const response = await fetch(url, { headers: { [header]: '1' }, cache: 'no-store', signal });
      if (response.redirected) {
        location.assign(url);
        return null;
      }
      if (response.headers.get(header) !== '1') throw new Error(translate(loadError));
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (!response.ok) {
        throw new Error(html.querySelector('[data-monthly-error]')?.textContent.trim() || translate(loadError));
      }
      const next = html.querySelector(selector);
      if (!next) throw new Error(translate(loadError));
      return next;
    }, next => {
      if (!next) return;
      const restoreFocus = addHistory && panel.contains(document.activeElement);
      panel.replaceWith(next);
      panel = next;
      if (addHistory && url.href !== location.href) history.pushState(history.state, '', url.href);
      document.dispatchEvent(new Event('mypark:monthly-attendance-updated'));
      if (restoreFocus) {
        const target = panel.querySelector(url.hash === '#calendar-day' ? '#calendar-day-heading' : '#attendance-calendar-heading');
        target?.focus();
      }
    }, error => {
      if (!addHistory) {
        location.reload();
        return;
      }
      notice.textContent = error.message;
      notice.hidden = false;
    }, busy => setBusy(busy, !busy && panel !== notice.closest(selector) ? 'Monthly attendance updated.' : ''));
  }

  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !panel.contains(form)) return;
    const url = new URL(form.action);
    if (url.origin !== location.origin || url.pathname !== location.pathname) return;
    url.search = new URLSearchParams(new FormData(form)).toString();
    event.preventDefault();
    load(url, true);
  });

  document.addEventListener('click', event => {
    const link = event.target.closest('a[data-monthly-link]');
    if (!link || !panel.contains(link) || event.defaultPrevented || event.button !== 0
        || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname !== location.pathname) return;
    event.preventDefault();
    load(url, true);
  });

  panel.parentElement.addEventListener('input', cancelOnEdit);
  panel.parentElement.addEventListener('change', cancelOnEdit);
  function cancelOnEdit(event) {
    if (!panel.contains(event.target)) return;
    request.cancel();
    setBusy(false);
  }

  window.addEventListener('popstate', () => load(new URL(location.href), false));
})();
