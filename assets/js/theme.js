(() => {
  'use strict';

  const storageKey = 'theme';
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
  let preference = systemTheme.matches ? 'dark' : 'light';

  try {
    const savedTheme = localStorage.getItem(storageKey);
    if (savedTheme === 'light' || savedTheme === 'dark') preference = savedTheme;
  } catch (_) {
    // The picker still works for this page when storage is unavailable.
  }

  function applyTheme() {
    document.documentElement.dataset.theme = preference;
  }

  applyTheme();

  document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (!themeToggle) return;

    function updateToggle() {
      const isDark = preference === 'dark';
      const label = isDark ? themeToggle.dataset.switchLight : themeToggle.dataset.switchDark;
      themeToggle.setAttribute('aria-label', label);
      themeToggle.setAttribute('aria-pressed', String(isDark));
      themeToggle.title = label;
    }

    updateToggle();
    themeToggle.addEventListener('click', () => {
      preference = preference === 'light' ? 'dark' : 'light';
      applyTheme();
      updateToggle();
      try {
        localStorage.setItem(storageKey, preference);
      } catch (_) {
        // Keep the selected theme for the current page.
      }
    });
  });
})();
