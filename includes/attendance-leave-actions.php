<form method="post" action="<?= e($leaveAction) ?>" class="ops-row-actions">
  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
  <input type="hidden" name="action" value="leave_review">
  <input type="hidden" name="id" value="<?= e($leave['id']) ?>">
  <button type="submit" name="decision" value="approved" class="ops-button attendance-approve" data-attendance-confirm="<?= te('Approve this leave request?') ?>"><?= te('Approve') ?></button>
  <button type="submit" name="decision" value="rejected" class="ops-button attendance-reject" data-attendance-confirm="<?= te('Reject this leave request?') ?>"><?= te('Reject') ?></button>
</form>
