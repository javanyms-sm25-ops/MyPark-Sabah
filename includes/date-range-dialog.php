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
  <div data-calendar-view>
    <p class="date-range-selection" data-range-selection hidden></p>
    <p class="date-range-instruction" data-range-instruction aria-live="polite"></p>
    <div class="date-range-navigation">
      <button type="button" data-calendar-prev aria-label="<?= te('Previous month') ?>"><span aria-hidden="true">‹</span></button>
      <div class="date-range-period">
        <button type="button" data-calendar-month data-picker-title="<?= te('Choose a month') ?>" aria-controls="date-range-period-picker"></button>
        <button type="button" data-calendar-year data-picker-title="<?= te('Choose a year') ?>" aria-controls="date-range-period-picker"></button>
      </div>
      <button type="button" data-calendar-next aria-label="<?= te('Next month') ?>"><span aria-hidden="true">›</span></button>
    </div>
    <h3 id="date-range-month" class="validation-sr-only" aria-live="polite"></h3>
    <div class="date-range-week" data-calendar-week aria-hidden="true"></div>
    <div class="date-range-days" data-calendar-days role="group" aria-labelledby="date-range-month"></div>
    <div class="date-range-actions">
      <button type="button" class="date-range-cancel" data-range-cancel><?= te('Cancel') ?></button>
      <button type="button" data-range-apply><?= te('Apply dates') ?></button>
    </div>
  </div>
  <div id="date-range-period-picker" hidden>
    <div class="date-range-navigation date-range-year-navigation" data-year-navigation hidden>
      <button type="button" data-prev-years aria-label="<?= te('Previous years') ?>"><span aria-hidden="true">‹</span></button>
      <strong data-year-page aria-live="polite"></strong>
      <button type="button" data-next-years aria-label="<?= te('Next years') ?>"><span aria-hidden="true">›</span></button>
    </div>
    <div class="date-range-choices" data-period-choices role="group" aria-labelledby="date-range-title"></div>
    <div class="date-range-actions">
      <button type="button" class="date-range-cancel" data-calendar-back><?= te('Back to calendar') ?></button>
    </div>
  </div>
</dialog>
<script src="assets/js/date-range.js" defer></script>
