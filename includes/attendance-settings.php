<details class="ops-panel attendance-panel" id="settings" <?= ($_POST['action'] ?? '') === 'save_settings' ? 'open' : '' ?>>
  <summary><?= te('Attendance Settings') ?></summary>
  <p><?= te('These settings apply to new attendance records. A lower GPS error limit requires more precise readings.') ?></p>
  <form method="post" action="admin/employee-attendance.php#settings" class="attendance-controls">
    <?= attendance_fields('save_settings') ?>
    <div class="attendance-fields">
      <label><?= te('Allowed distance (m)') ?><input type="number" name="radius_m" min="25" max="5000" value="<?= $settings['radius_m'] ?>" required></label>
      <label><?= te('Maximum GPS error (m)') ?><input type="number" name="accuracy_m" min="5" max="1000" value="<?= $settings['accuracy_m'] ?>" required></label>
      <label><?= te('Late grace period (minutes)') ?><input type="number" name="grace_minutes" min="0" max="120" value="<?= $settings['grace_minutes'] ?>" required></label>
    </div>
    <button class="btn"><?= te('Save settings') ?></button>
  </form>
</details>

<details class="ops-panel attendance-panel" id="access" <?= in_array($_POST['action'] ?? '', ['grant_scope', 'revoke_scope'], true) ? 'open' : '' ?>>
  <summary><?= te('Admin Location Access') ?></summary>
  <p><?= te('Admins can only view, schedule, review, and export attendance for locations you assign here.') ?></p>
  <?php if ($admins && $locations): ?>
    <form method="post" action="admin/employee-attendance.php#access" class="attendance-controls">
      <?= attendance_fields('grant_scope') ?>
      <div class="attendance-fields">
        <label><?= te('Admin') ?><select name="admin_id" required><option value=""><?= te('Choose an Admin') ?></option>
          <?php foreach ($admins as $account): ?><option value="<?= $account['id'] ?>"><?= e($account['name']) ?></option><?php endforeach; ?>
        </select></label>
        <label><?= te('Parking location') ?><select name="lot_id" required><option value=""><?= te('Choose a parking location') ?></option>
          <?php foreach ($locations as $lot): ?><option value="<?= $lot['id'] ?>"><?= e($lot['name']) ?></option><?php endforeach; ?>
        </select></label>
      </div>
      <button class="btn"><?= te('Grant access') ?></button>
    </form>
    <h3><?= te('Current assignments') ?></h3>
    <?php if (!$grants): ?><p><?= te('No Admin location assignments yet.') ?></p><?php endif; ?>
    <?php foreach ($grants as $grant): ?>
      <form method="post" action="admin/employee-attendance.php#access" class="attendance-grant">
        <?= attendance_fields('revoke_scope') ?>
        <input type="hidden" name="admin_id" value="<?= $grant['admin_id'] ?>"><input type="hidden" name="lot_id" value="<?= $grant['lot_id'] ?>">
        <span><?= e(($accounts[$grant['admin_id']]['name'] ?? '#' . $grant['admin_id']) . ' · ' . ($locations[$grant['lot_id']]['name'] ?? '#' . $grant['lot_id'])) ?></span>
        <?php if (isset($admins[$grant['admin_id']], $locations[$grant['lot_id']])): ?><button class="btn attendance-secondary"><?= te('Remove access') ?></button><?php endif; ?>
      </form>
    <?php endforeach; ?>
  <?php else: ?><p><?= te('Add an active Admin and a parking location to assign access.') ?></p><?php endif; ?>
</details>

<details class="ops-panel attendance-panel" id="audit">
  <summary><?= te('Attendance Audit Trail') ?></summary>
  <p><?= te('The 50 most recent administrative changes, newest first.') ?></p>
  <?php if ($audit): ?>
    <div class="attendance-table" tabindex="0" role="region" aria-label="<?= te('Attendance Audit Trail') ?>"><table>
      <thead><tr><?php foreach (['Date and time', 'Admin', 'Action', 'Shift ID', 'Previous value', 'Updated value', 'Reason'] as $heading): ?><th scope="col"><?= te($heading) ?></th><?php endforeach; ?></tr></thead>
      <tbody><?php foreach ($audit as $change): ?>
        <tr><td><?= e(attendance_clock($change['created_at'])) ?> MYT</td>
          <td><?= e(($accounts[$change['admin_id']]['name'] ?? '—') . ' · #' . $change['admin_id']) ?></td>
          <td><?= te($change['action']) ?></td>
          <td><?php if ($change['shift_id']): ?><a class="btn btn-secondary" href="admin/employee-attendance.php?shift=<?= $change['shift_id'] ?>#record">#<?= $change['shift_id'] ?></a><?php else: ?>—<?php endif; ?></td>
          <td><?= attendance_audit_values($change['before_value']) ?></td><td><?= attendance_audit_values($change['after_value']) ?></td><td><?= e($change['reason']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p><?= te('No administrative changes recorded yet.') ?></p><?php endif; ?>
</details>
