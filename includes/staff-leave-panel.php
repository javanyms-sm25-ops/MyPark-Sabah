<?php
$leaveRequests = staff_leave_rows($actor);
$view = ajax_table_view('staff-leave', $leaveRequests, ['start_date' => 'Start date', 'end_date' => 'End date', 'status' => 'Status'], [
    'filter' => ['label' => 'Status', 'field' => 'status', 'choices' => ['' => 'All statuses'] + STAFF_LEAVE_STATUSES],
    'empty' => 'No leave requests yet.',
]);
$reasonError = in_array($leaveError, [STAFF_LEAVE_REASON_REQUIRED, STAFF_LEAVE_REASON_TOO_LONG], true) ? $leaveError : '';
?>
<section class="staff-card" id="leave-requests" aria-labelledby="leave-requests-heading">
  <div class="staff-section-heading"><h2 id="leave-requests-heading"><?= te('My leave requests') ?></h2></div>
  <form method="post" action="staff.php#leave-requests" class="staff-leave-form" data-date-range>
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="action" value="leave_submit">
    <?php if ($leaveError !== '' && $reasonError === ''): ?><p class="staff-form-error" role="alert"><?= te($leaveError) ?></p><?php endif; ?>
    <div class="staff-leave-dates">
      <?php foreach (['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label): ?>
        <div class="staff-field">
          <label for="leave-<?= e($field) ?>"><?= te($label) ?></label>
          <input id="leave-<?= e($field) ?>" type="date" name="<?= e($field) ?>" value="<?= e($leaveError !== '' ? ($_POST[$field] ?? '') : '') ?>"
            <?= $field === 'start_date' ? 'data-date-range-start' : 'data-date-range-end' ?> required>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="staff-field">
      <label for="leave-reason"><?= te('Reason (required)') ?></label>
      <textarea id="leave-reason" name="reason" rows="2" maxlength="500" required
        aria-describedby="leave-reason-error" aria-invalid="<?= $reasonError === '' ? 'false' : 'true' ?>"><?= e($leaveError !== '' ? ($_POST['reason'] ?? '') : '') ?></textarea>
      <p class="staff-field-error" id="leave-reason-error" aria-live="polite"><?= te($reasonError) ?></p>
    </div>
    <button type="submit" class="staff-button"><?= te('Submit leave request') ?></button>
  </form>
  <?php ajax_table_begin($view); ?>
    <div class="staff-table-scroll" role="region" aria-labelledby="leave-requests-heading" tabindex="0">
      <table class="staff-table staff-leave-table">
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $leave): ?>
            <tr>
              <td><time datetime="<?= e($leave['start_date']) ?>"><?= e($leave['start_date']) ?></time></td>
              <td><time datetime="<?= e($leave['end_date']) ?>"><?= e($leave['end_date']) ?></time></td>
              <td><span class="staff-badge staff-badge--<?= e(match ($leave['status']) {
                  'approved' => 'paid', 'rejected' => 'void', default => 'unpaid',
              }) ?>"><?= te(STAFF_LEAVE_STATUSES[$leave['status']]) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php ajax_table_empty($view); ?>
        </tbody>
      </table>
    </div>
  <?php ajax_table_end($view); ?>
</section>
