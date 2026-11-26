<?php
[$in, $out] = attendance_times($row);
?>
<dl class="attendance-facts">
  <div><dt><?= te('Assigned Location') ?></dt><dd><?= e($row['lot_name']) ?></dd></div>
  <div><dt><?= te('Attendance date') ?></dt><dd><?= e($row['shift_date']) ?> · MYT</dd></div>
  <div><dt><?= te('Scheduled shift') ?></dt><dd><?= e(attendance_clock($row['starts_at'])) ?> – <?= e(attendance_clock($row['ends_at'])) ?></dd></div>
  <div><dt><?= te('Check In') ?></dt><dd><?= e(attendance_clock($in)) ?> <?= attendance_badge(attendance_punctuality($row)) ?></dd></div>
  <div><dt><?= te('Check Out') ?></dt><dd><?= e(attendance_clock($out)) ?></dd></div>
  <div><dt><?= te('Working duration') ?></dt><dd><?= e(attendance_duration($row)) ?></dd></div>
  <div><dt><?= te('Check-in location') ?></dt><dd><?= attendance_badge($row['in_zone']) ?></dd></div>
  <div><dt><?= te('Check-out location') ?></dt><dd><?= attendance_badge($row['out_zone']) ?></dd></div>
</dl>
<details class="attendance-details" <?= $actor['role'] === 'staff' && !isset($_GET['shift']) && !isset($shiftId) ? '' : 'open' ?>>
  <summary><?= te('View Attendance Details') ?></summary>
  <div class="attendance-evidence">
    <div>
      <h3><?= te('Original check-in evidence') ?></h3>
      <p><?= e($row['lot_name']) ?> · <?= e(attendance_clock($row['checked_in'])) ?> MYT</p>
      <?php if ($row['photo_available']): ?>
        <img class="attendance-preview" src="<?= e($photoUrl) ?>" alt="<?= te('Check-in photo') ?>" loading="lazy">
      <?php else: ?><p><?= te('No stored photo for this shift.') ?></p><?php endif; ?>
      <?= attendance_location_evidence($row, 'in') ?>
    </div>
    <div>
      <h3><?= te('Original check-out evidence') ?></h3>
      <p><?= e(attendance_clock($row['checked_out'])) ?> MYT</p>
      <?= attendance_location_evidence($row, 'out') ?>
    </div>
  </div>
</details>
<?php if ($requests): ?>
  <section class="attendance-reviews" aria-label="<?= te('Attendance reviews') ?>">
    <h3><?= te('Attendance reviews') ?></h3>
    <?php foreach ($requests as $request):
      $waiting = $request['status'] === 'pending' && $request['clarified_at'] !== null && $request['replied_at'] === null;
    ?>
      <article class="attendance-review">
        <div class="attendance-heading"><strong><?= te(match ($request['kind']) {
          'exception' => 'Camera exception', 'correction' => 'Correction request', default => 'Location review',
        }) ?></strong><?= attendance_badge($waiting ? 'clarification' : $request['status']) ?></div>
        <p><?= e(attendance_clock($request['created_at'])) ?> MYT · <?= $request['kind'] === 'evidence' ? te($request['reason']) : e($request['reason']) ?></p>
        <?php if ($request['proposed_in']): ?><p><?= te('Proposed check-in') ?> · <?= e(attendance_clock($request['proposed_in'])) ?></p><?php endif; ?>
        <?php if ($request['proposed_out']): ?><p><?= te('Proposed check-out') ?> · <?= e(attendance_clock($request['proposed_out'])) ?></p><?php endif; ?>
        <?php if ($request['clarified_at'] !== null): ?>
          <p><strong><?= te('Clarification requested') ?></strong> · <?= e(attendance_clock($request['clarified_at'])) ?> MYT<br><?= e($request['clarification_reason']) ?></p>
          <?php if ($request['replied_at'] !== null): ?>
            <p><strong><?= te('Employee reply') ?></strong> · <?= e(attendance_clock($request['replied_at'])) ?> MYT<br><?= e($request['clarification_reply']) ?></p>
          <?php elseif ($waiting && $actor['role'] === 'staff'): ?>
            <form method="post" class="attendance-controls">
              <?= attendance_fields('reply', $row['id']) ?>
              <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
              <label><?= te('Your reply') ?><textarea name="reason" maxlength="500" required></textarea></label>
              <button class="staff-button"><?= te('Send reply') ?></button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($request['reviewed_at']): ?>
          <p><strong><?= te('Reviewer’s reason') ?></strong> · <?= e($request['decision_reason']) ?><br><?= e(attendance_clock($request['reviewed_at'])) ?> MYT
          <?php if ($actor['role'] !== 'staff'): ?> · <?= e($accounts[$request['reviewer_id']]['name'] ?? '#' . $request['reviewer_id']) ?><?php endif; ?></p>
        <?php elseif ($actor['role'] !== 'staff'): ?>
          <form method="post" action="<?= e($reviewAction ?? 'admin/employee-attendance.php?shift=' . $row['id']) ?>" class="attendance-controls">
            <?= attendance_fields('review', $row['id']) ?>
            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
            <label><?= te('Reason for decision') ?><textarea name="reason" maxlength="500" required></textarea></label>
            <div class="attendance-actions">
              <button class="btn" name="decision" value="approved"><?= te('Approve') ?></button>
              <button class="btn attendance-secondary" name="decision" value="rejected"><?= te('Reject') ?></button>
              <?php if ($request['clarified_at'] === null): ?><button class="btn btn-secondary" name="decision" value="clarification"><?= te('Request clarification') ?></button><?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
