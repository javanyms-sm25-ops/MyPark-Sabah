<link rel="stylesheet" href="assets/css/date-range.css">
<template id="date-range-trigger-template">
  <button type="button" class="date-range-trigger" aria-haspopup="dialog" aria-controls="date-range-picker">
    <span></span>
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
      <rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M7.5 3v5m9-5v5M4 10.5h16"/>
    </svg>
  </button>
</template>
<dialog id="date-range-picker" class="date-range-picker" aria-labelledby="date-range-title" data-today="<?= e(date('Y-m-d')) ?>">
  <h2 id="date-range-title"><?= te('Choose date range') ?></h2>
  <p class="date-range-selection" data-range-selection hidden></p>
  <p class="date-range-instruction" data-range-instruction aria-live="polite"></p>
  <div class="date-range-navigation">
    <button type="button" data-calendar-prev aria-label="<?= te('Previous month') ?>"><span aria-hidden="true">‹</span></button>
    <h3 id="date-range-month" aria-live="polite"></h3>
    <button type="button" data-calendar-next aria-label="<?= te('Next month') ?>"><span aria-hidden="true">›</span></button>
  </div>
  <div class="date-range-week" data-calendar-week aria-hidden="true"></div>
  <div class="date-range-days" data-calendar-days role="group" aria-labelledby="date-range-month"></div>
  <div class="date-range-actions">
    <button type="button" class="date-range-cancel" data-range-cancel><?= te('Cancel') ?></button>
    <button type="button" data-range-apply><?= te('Apply dates') ?></button>
  </div>
</dialog>
<script src="assets/js/date-range.js" defer></script>
