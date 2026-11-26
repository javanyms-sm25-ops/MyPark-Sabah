<?php
$okuFilter = oku_queue_filter($_GET['oku_status'] ?? null);
$view = ajax_table_view('oku-reviews', oku_review_rows($actor, 'all'), [
    'name' => 'Member', 'submitted_at' => 'Submitted', 'status' => 'Status', 'actions' => 'Review',
], [
    'filter' => ['label' => 'Status', 'field' => 'status', 'choices' => OKU_STATUS_LABELS + ['' => 'All statuses'],
        'default' => $okuFilter === 'all' ? '' : $okuFilter],
    'empty' => 'No OKU submissions match this filter.',
]);
$okuFilter = $view['selected'] === '' ? 'all' : $view['selected'];
?>
<section class="ops-panel" id="oku-verification" aria-labelledby="oku-queue-heading">
  <div class="ops-panel-heading"><h2 id="oku-queue-heading"><?= te('OKU Verification') ?></h2></div>
  <?php if ($actor['role'] === 'superadmin'): ?>
    <details class="ops-oku-access">
      <summary><?= te('Manage Admin review access') ?></summary>
      <?php foreach (MyParkData::$records['demo_users'] as $account): ?>
        <?php if ($account['role'] !== 'admin') continue; ?>
        <?php $granted = ($account['can_review_oku'] ?? false) === true; ?>
        <form method="post" action="admin/dashboard.php" class="ops-oku-access-row">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="action" value="oku_reviewer_access">
          <input type="hidden" name="admin_id" value="<?= e($account['id']) ?>">
          <input type="hidden" name="oku_status" value="<?= e($okuFilter) ?>">
          <span><?= e($account['name']) ?> · <?= te($granted ? 'Review access granted' : 'Review access disabled') ?></span>
          <button type="submit" name="access" class="ops-button" value="<?= $granted ? 'revoke' : 'grant' ?>"><?= te($granted ? 'Revoke review access' : 'Grant review access') ?></button>
        </form>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>
  <p class="ops-caption"><?= te('Review proof for MyPark Sabah parking eligibility. Keep document numbers out of rejection reasons.') ?></p>
  <?php ajax_table_begin($view); ?>
    <div class="ops-table-scroll" role="region" aria-labelledby="oku-queue-heading" tabindex="0">
      <table class="ops-table ops-oku-table">
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $row): ?>
            <tr>
              <td><?= e($row['name']) ?></td>
              <td><time datetime="<?= e(date(DATE_ATOM, $row['submitted_at'])) ?>"><?= e(date('d M Y, H:i', $row['submitted_at'])) ?> MYT</time></td>
              <td><?= te(OKU_STATUS_LABELS[$row['status']]) ?></td>
              <td>
                <?php if ($row['status'] === 'pending'): ?>
                  <form method="post" action="api/oku-proof.php" target="_blank" rel="noopener">
                    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="submission_id" value="<?= e($row['id']) ?>">
                    <button class="ops-button" type="submit"><?= te('Open proof in a new tab') ?></button>
                  </form>
                  <form method="post" action="admin/dashboard.php" class="ops-oku-decision">
                    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="action" value="oku_decide">
                    <input type="hidden" name="submission_id" value="<?= e($row['id']) ?>">
                    <input type="hidden" name="oku_status" value="<?= e($okuFilter) ?>">
                    <label><?= te('Rejection reason (required to reject)') ?><textarea name="reason" maxlength="500" rows="2"></textarea></label>
                    <div class="row">
                      <button type="submit" name="decision" value="approved" class="ops-button" data-confirm="<?= te('Approve this member for OKU parking in MyPark Sabah?') ?>"><?= te('Approve') ?></button>
                      <button type="submit" name="decision" value="rejected" class="ops-button"><?= te('Reject') ?></button>
                    </div>
                  </form>
                <?php else: ?>
                  <span><?= te('Reviewed by {name}', ['name' => $row['reviewer_name']]) ?></span><br>
                  <time datetime="<?= e(date(DATE_ATOM, $row['reviewed_at'])) ?>"><?= e(date('d M Y, H:i', $row['reviewed_at'])) ?> MYT</time>
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
