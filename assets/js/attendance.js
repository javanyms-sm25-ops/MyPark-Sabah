(() => {
  'use strict';

  const panel = document.querySelector('[data-leave-review-panel]');
  const dialog = document.getElementById('attendance-review-dialog');
  if (!panel || typeof dialog?.showModal !== 'function') return;

  const title = document.getElementById('attendance-review-title');
  const staffName = dialog.querySelector('[data-review-staff]');
  const leaveDates = dialog.querySelector('[data-review-dates]');
  const cancel = dialog.querySelector('[data-review-cancel]');
  const confirm = dialog.querySelector('[data-review-confirm]');
  let trigger;
  let confirming = false;
  let submitting = false;

  panel.addEventListener('submit', event => {
    const button = event.submitter;
    if (event.defaultPrevented || !button?.hasAttribute('data-attendance-confirm')) return;
    if (confirming && !submitting && button === trigger) {
      submitting = true;
      cancel.disabled = true;
      confirm.disabled = true;
      confirm.textContent = dialog.dataset.savingLabel;
      return;
    }

    event.preventDefault();
    if (dialog.open || submitting) return;

    trigger = button;
    const row = button.closest('[data-leave-review-item]');
    const dates = Array.from(row.querySelectorAll('[data-leave-date]'), time => time.textContent.trim());
    title.textContent = button.dataset.attendanceConfirm;
    staffName.textContent = row.querySelector('.attendance-name').textContent.trim();
    leaveDates.textContent = [...new Set(dates)].join(' – ');
    dialog.dataset.decision = button.value;
    confirm.textContent = button.textContent.trim();
    dialog.showModal();
    cancel.focus();
  });

  function closeDialog() {
    dialog.close();
    trigger?.focus();
    trigger = null;
  }

  cancel.addEventListener('click', closeDialog);
  confirm.addEventListener('click', () => {
    if (!trigger || submitting) return;
    confirming = true;
    try {
      trigger.form.requestSubmit(trigger);
    } finally {
      confirming = false;
    }
  });

  dialog.addEventListener('cancel', event => {
    event.preventDefault();
    if (!submitting) closeDialog();
  });
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    submitting = false;
    cancel.disabled = false;
    confirm.disabled = false;
    if (dialog.open) closeDialog();
  });
})();
