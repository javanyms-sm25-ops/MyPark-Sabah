<dialog class="attendance-review-dialog" id="attendance-review-dialog" aria-labelledby="attendance-review-title"
        aria-describedby="attendance-review-summary" data-saving-label="<?= te('Saving…') ?>">
  <h2 id="attendance-review-title"></h2>
  <div class="attendance-review-summary" id="attendance-review-summary">
    <strong data-review-staff></strong>
    <span data-review-dates></span>
  </div>
  <div class="attendance-review-actions">
    <button type="button" data-review-cancel autofocus><?= te('Cancel') ?></button>
    <button type="button" data-review-confirm></button>
  </div>
</dialog>
