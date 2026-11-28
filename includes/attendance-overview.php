<?php if (!$super):
    $pendingLeave = array_filter(staff_leave_rows($actor), static fn(array $leave): bool => $leave['status'] === 'pending');
    $leaveAction = attendance_report_links($tabFilters, $page, ['tab' => 'overview']) . '#attendance-leave-preview';
?>
  <section class="ops-panel attendance-panel attendance-leave-preview" id="attendance-leave-preview"
           aria-labelledby="leave-preview-heading" data-leave-review-panel>
    <div class="attendance-heading">
      <div class="attendance-section-title">
        <h2 id="leave-preview-heading"><?= te('Pending leave requests') ?></h2>
        <span class="ops-count"><?= te('{count} pending', ['count' => count($pendingLeave)]) ?></span>
      </div>
      <a class="btn btn-secondary" href="admin/attendance.php"><?= te('View all leave requests') ?></a>
    </div>
    <?php if (!$pendingLeave): ?>
      <p class="muted"><?= te('No pending leave requests') ?></p>
    <?php else: ?>
      <ul class="attendance-leave-list">
        <?php foreach (array_slice($pendingLeave, 0, 3) as $leave):
            preg_match('/[\p{L}\p{N}]/u', $leave['staff_name'], $initial);
            $dates = array_unique([$leave['start_date'], $leave['end_date']]);
        ?>
          <li data-leave-review-item>
            <span class="attendance-leave-avatar" aria-hidden="true"><?= e(mb_strtoupper($initial[0] ?? '—', 'UTF-8')) ?></span>
            <div class="attendance-leave-person">
              <strong class="attendance-name"><?= e($leave['staff_name']) ?></strong>
              <p class="muted"><?php foreach (array_values($dates) as $index => $date): ?><?= $index ? ' – ' : '' ?><time data-leave-date datetime="<?= e($date) ?>"><?= e(date('j M Y', strtotime($date))) ?></time><?php endforeach; ?></p>
              <?php if ($leave['reason'] !== ''): ?><p class="attendance-leave-reason"><?= e($leave['reason']) ?></p><?php endif; ?>
            </div>
            <?php require __DIR__ . '/attendance-leave-actions.php'; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($week):
    $weekStart = new DateTimeImmutable($week['filters']['from']);
    $weekEnd = new DateTimeImmutable($week['filters']['to']);
?>
  <section class="ops-panel attendance-panel attendance-week" aria-labelledby="attendance-week-heading">
    <div class="attendance-heading">
      <h2 id="attendance-week-heading"><?= te('Team attendance this week') ?></h2>
      <p class="muted"><?= e($weekStart->format('j M')) ?> – <?= e($weekEnd->format('j M Y')) ?></p>
    </div>
    <ul class="attendance-week-days">
      <?php foreach (new DatePeriod($weekStart, new DateInterval('P1D'), $weekEnd->modify('+1 day')) as $day):
          $date = $day->format('Y-m-d');
          $counts = $week['daily'][$date] ?? ['present' => 0, 'absent' => 0];
          $rate = attendance_report_rate($counts['present'], $counts['present'] + $counts['absent']);
          $state = $rate === null ? 'empty' : ($rate >= 100 ? 'complete' : 'partial');
      ?>
        <li>
          <time datetime="<?= e($date) ?>"<?= $date === $today ? ' aria-current="date"' : '' ?>>
            <?= te($day->format('D')) ?> <span><?= e($day->format('j')) ?></span>
          </time>
          <strong data-rate-state="<?= e($state) ?>"><?= $rate === null ? '—' : e(rtrim(rtrim(number_format($rate, 1), '0'), '.') . '%') ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
    <small class="muted"><?= te('A dash means there are no completed, settled attendance records for that day.') ?></small>
  </section>
<?php endif; ?>
