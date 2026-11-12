/* Initial page skeletons. Loaded in <head> before content is painted. */
(function () {
  'use strict';

  const root = document.documentElement;
  let finished = false;
  let timeout;

  function revealContent() {
    finished = true;
    window.clearTimeout(timeout);
    root.classList.remove('page-loading');
    const main = document.querySelector('main');
    if (main) main.removeAttribute('aria-busy');
  }

  // Fail open: font/network failures must never leave the page masked.
  timeout = window.setTimeout(revealContent, 2500);
  root.classList.add('page-loading');
  window.addEventListener('pageshow', revealContent, { once: true });
  window.addEventListener('beforeprint', revealContent);

  function contentReady() {
    if (finished) return;
    const main = document.querySelector('main');
    if (main) main.setAttribute('aria-busy', 'true');

    // Wait only for actual typography readiness, not maps or third-party images.
    if (document.fonts && document.fonts.status === 'loading') {
      document.fonts.ready.then(revealContent, revealContent);
    } else {
      revealContent();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', contentReady, { once: true });
  } else {
    contentReady();
  }
}());
