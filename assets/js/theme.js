(() => {
  'use strict';

  const storageKey = 'theme';
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
  let preference = 'system';

  try {
    const savedTheme = localStorage.getItem(storageKey);
    if (savedTheme === 'light' || savedTheme === 'dark') preference = savedTheme;
  } catch (_) {
    // The picker still works for this page when storage is unavailable.
  }

  function applyTheme() {
    document.documentElement.dataset.theme = preference === 'system'
      ? (systemTheme.matches ? 'dark' : 'light')
      : preference;
  }

  applyTheme();
  systemTheme.addEventListener('change', () => {
    if (preference === 'system') applyTheme();
  });

  document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-theme-picker]');
    if (!picker) return;

    const trigger = picker.querySelector('summary');
    picker.querySelector(`input[value="${preference}"]`).checked = true;

    picker.addEventListener('change', (event) => {
      preference = event.target.value;
      applyTheme();
      try {
        localStorage.setItem(storageKey, preference);
      } catch (_) {
        // Keep the selected theme for the current page.
      }
    });

    picker.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || !picker.open) return;
      event.preventDefault();
      picker.open = false;
      trigger.focus();
    });

    document.addEventListener('click', (event) => {
      if (picker.open && !picker.contains(event.target)) picker.open = false;
    });
  });
})();
