<button class="theme-toggle" type="button" data-theme-toggle
  data-switch-dark="<?= te('Switch to dark mode') ?>"
  data-switch-light="<?= te('Switch to light mode') ?>"
  aria-label="<?= te('Toggle theme') ?>">
  <svg class="theme-toggle__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" focusable="false">
    <circle cx="12" cy="12" r="4" />
    <path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.4 1.4m11.2 11.2L19 19M5 19l1.4-1.4M17.6 6.4 19 5" />
  </svg>
  <svg class="theme-toggle__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="M20.9 13.4A9 9 0 0 1 10.6 3.1a9 9 0 1 0 10.3 10.3Z" />
  </svg>
  <span class="theme-toggle__light"><?= te('Light mode') ?></span>
  <span class="theme-toggle__dark"><?= te('Dark mode') ?></span>
</button>
