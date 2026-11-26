<?php
$view = ajax_table_view('leave-review', $leaveRequests, [
    'staff_name' => 'Staff', 'start_date' => 'Start date', 'end_date' => 'End date', 'reason' => 'Reason',
    'status' => 'Status', 'actions' => 'Actions',
], [
    'filter' => ['label' => 'Status', 'field' => 'status', 'choices' => ['' => 'All statuses'] + STAFF_LEAVE_STATUSES],
    'empty' => $dateQuery !== '' ? 'No matching leave requests.' : 'No leave requests yet.',
]);
?>
<section class="ops-panel attendance-panel" id="attendance-management" aria-labelledby="attendance-heading">
  <div class="ops-panel-heading attendance-panel-heading">
    <h2 id="attendance-heading"><?= te('Leave requests') ?></h2>
    <span class="attendance-pending-count"><?= te('{count} pending', ['count' => count(array_filter($leaveRequests, static fn($leave) => $leave['status'] === 'pending'))]) ?></span>
  </div>
  <form method="get" action="admin/attendance.php" class="attendance-date-filter" aria-label="<?= te('Filter by leave dates') ?>" data-date-range>
    <div>
      <label for="attendance-from"><?= te('Start date') ?></label>
      <input type="date" id="attendance-from" name="from" value="<?= e($from) ?>" aria-describedby="attendance-date-help" data-date-range-start>
    </div>
    <div>
      <label for="attendance-to"><?= te('End date') ?></label>
      <input type="date" id="attendance-to" name="to" value="<?= e($to) ?>" aria-describedby="attendance-date-help" data-date-range-end>
    </div>
    <button type="submit" class="ops-button" data-date-range-submit><?= te('Apply dates') ?></button>
    <?php if ($dateQuery !== ''): ?><a class="btn btn-secondary" href="admin/attendance.php"><?= te('Reset dates') ?></a><?php endif; ?>
    <p id="attendance-date-help"><?= te('Shows leave requests that overlap the selected dates.') ?></p>
  </form>
  <?php if ($rangeError !== ''): ?><p class="ops-notice" role="alert"><?= te($rangeError) ?></p><?php endif; ?>
  <?php if ($reviewError !== ''): ?><p class="ops-notice" role="alert"><?= te($reviewError) ?></p><?php endif; ?>
  <?php ajax_table_begin($view); ?>
    <div class="ops-table-scroll" role="region" aria-labelledby="attendance-heading" tabindex="0">
      <table class="ops-table ops-leave-table">
        <colgroup>
          <col class="attendance-col-staff">
          <col class="attendance-col-date" span="2">
          <col>
          <col class="attendance-col-status">
          <col class="attendance-col-actions">
        </colgroup>
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $leave): ?>
            <tr>
              <td><strong class="attendance-name"><?= e($leave['staff_name']) ?></strong>
                <time class="ops-leave-submitted" datetime="<?= e($leave['submitted_at']) ?>"><?= e(date('d M Y', strtotime($leave['submitted_at']))) ?></time>
              </td>
              <td><time data-leave-date datetime="<?= e($leave['start_date']) ?>"><?= e(date('d M Y', strtotime($leave['start_date']))) ?></time></td>
              <td><time data-leave-date datetime="<?= e($leave['end_date']) ?>"><?= e(date('d M Y', strtotime($leave['end_date']))) ?></time></td>
              <td class="ops-leave-reason"><?= $leave['reason'] !== '' ? e($leave['reason']) : '—' ?></td>
              <td><span class="ops-badge ops-badge--<?= e(match ($leave['status']) {
                  'approved' => 'active', 'rejected' => 'blocked', default => 'pending',
              }) ?>"><?= te(STAFF_LEAVE_STATUSES[$leave['status']]) ?></span></td>
              <td data-column="actions">
                <?php if ($leave['status'] === 'pending'): ?>
                  <form method="post" action="<?= e('admin/' . $attendanceTarget) ?>" class="ops-row-actions">
                    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="action" value="leave_review">
                    <input type="hidden" name="id" value="<?= e($leave['id']) ?>">
                    <button type="submit" name="decision" value="approved" class="ops-button attendance-approve" data-attendance-confirm="<?= te('Approve this leave request?') ?>"><?= te('Approve') ?></button>
                    <button type="submit" name="decision" value="rejected" class="ops-button attendance-reject" data-attendance-confirm="<?= te('Reject this leave request?') ?>"><?= te('Reject') ?></button>
                  </form>
                <?php else: ?>
                  <span class="attendance-reviewer"><?= te('Reviewed by {name}', ['name' => $leave['reviewer_name']]) ?></span>
                  <time class="ops-leave-submitted" datetime="<?= e($leave['reviewed_at']) ?>"><?= e(date('d M Y, H:i', strtotime($leave['reviewed_at']))) ?> MYT</time>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php ajax_table_empty($view); ?>
        </tbody>
      </table>
    </div>
  <?php ajax_table_end($view); ?>
</section>
