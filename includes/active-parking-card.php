<?php
$parkingNow = time();
$parkingSessions = [];
foreach ($bookings as $booking) {
    if ($booking['status'] !== 'active') continue;
    $end = booking_expiry($booking);
    if ($end !== null && $end <= $parkingNow) continue;
    $hours = filter_var($booking['hours'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    $parkingSessions[] = ['booking' => $booking, 'end' => $end, 'duration' => $hours ? $hours * 3600 : null];
}
usort($parkingSessions, static fn($first, $second) => ($first['end'] ?? PHP_INT_MAX) <=> ($second['end'] ?? PHP_INT_MAX));
$parkingCount = count($parkingSessions);
$focusSession = array_search($openSessionId ?? false, array_column(array_column($parkingSessions, 'booking'), 'id'), true);
if ($focusSession !== false && $focusSession > 0) {
    // Keep the action's error next to the session it applies to.
    array_unshift($parkingSessions, ...array_splice($parkingSessions, $focusSession, 1));
}
?>
<section class="card active-parking" aria-labelledby="active-parking-title" data-active-parking
    data-server-time="<?= e($parkingNow) ?>" data-reminder-seconds="<?= e(PARKING_REMINDER_SECONDS) ?>">
  <div class="active-parking__heading">
    <span class="active-parking__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        <path d="M5 17v2m14-2v2M4 11l1.5-4A2 2 0 0 1 7.4 5h9.2a2 2 0 0 1 1.9 2l1.5 4M7 14h.01M17 14h.01"/>
        <rect x="3" y="10" width="18" height="7" rx="2"/>
      </svg>
    </span>
    <h2 id="active-parking-title" tabindex="-1"><?= te('Active parking') ?></h2>
  </div>
  <?php if (!empty($managementError) && $focusSession === false): ?>
    <p class="active-parking__error" role="alert" tabindex="-1" data-parking-action-error><?= te($managementMessage) ?></p>
  <?php endif; ?>
  <?php foreach ($parkingSessions as $index => ['booking' => $session, 'end' => $end, 'duration' => $duration]): ?>
    <?php
      $remaining = $end === null ? null : $end - $parkingNow;
      $endingSoon = $remaining !== null && $remaining <= PARKING_REMINDER_SECONDS;
      $endDate = $end === null || date('Y-m-d', $end) === date('Y-m-d', $parkingNow) ? '' : date('Y-m-d', $end);
      $showActionError = !empty($managementError) && $openSessionId === $session['id'];
      $deadline = booking_cancellation_deadline($session);
      $canCancel = $deadline !== null && $parkingNow < $deadline;
      $deadlineLabel = $deadline === null ? '' : date(
          date('Y-m-d', $deadline) === date('Y-m-d', $parkingNow) ? 'g:i:s A' : 'j M, g:i:s A',
          $deadline
      ) . ' MYT';
      $cancelHintId = 'dashboard-cancellation-' . $session['id'];
      $bayType = parking_booking_bay_type($session);
      $destination = $bayType === 'regular' ? 'oku' : 'regular';
      $needsApproval = $destination === 'oku' && !oku_is_approved($userId);
    ?>
    <div class="active-parking__session" data-dashboard-parking-session data-expires="<?= e($end ?? '') ?>"
        <?= $endingSoon ? 'data-ending-soon' : '' ?> <?= $index > 0 ? 'hidden' : '' ?>>
      <div class="active-parking__clock">
        <?php if ($end !== null): ?>
          <p class="active-parking__label" data-parking-time-label><?= te($endingSoon ? 'Parking ends soon' : 'Time remaining') ?></p>
          <p class="active-parking__countdown" data-parking-countdown hidden></p>
          <?php if ($duration !== null): ?>
            <progress class="active-parking__progress" value="<?= e(min($duration, $remaining)) ?>"
                max="<?= e($duration) ?>" aria-label="<?= te('Time remaining') ?>"></progress>
          <?php endif; ?>
          <p class="active-parking__end">
            <span><?= te('Ends at') ?></span>
            <time datetime="<?= e(date(DATE_ATOM, $end)) ?>"><?= e(date('g:i A', $end)) ?> MYT<?= $endDate !== '' ? ' · ' . e($endDate) : '' ?></time>
          </p>
        <?php else: ?>
          <p class="active-parking__label"><?= te('End time unavailable') ?></p>
        <?php endif; ?>
        <p id="<?= e($cancelHintId) ?>" class="active-parking__deadline" data-cancellation-policy aria-live="polite">
          <?php if ($canCancel): ?>
            <?= te('You can cancel until {time}.', ['time' => $deadlineLabel]) ?>
          <?php else: ?>
            <?= te($deadline === null ? 'Cancellation deadline unavailable.' : PARKING_CANCELLATION_CLOSED) ?>
          <?php endif; ?>
        </p>
      </div>
      <div class="active-parking__details">
        <h3><?= e($session['lot']) ?></h3>
        <p>
          <span class="active-parking__plate"><span class="validation-sr-only"><?= te('Vehicle plate') ?> </span><?= e($session['plate']) ?></span>
          <span class="muted"><?= te(PARKING_BAY_TYPES[parking_booking_bay_type($session)]) ?></span>
        </p>
      </div>
      <div class="active-parking__actions">
        <a class="btn" href="ticket.php?id=<?= e($session['id']) ?>"><?= te('View ticket') ?> <span aria-hidden="true">→</span></a>
        <details class="active-parking__manage" <?= $showActionError ? 'open' : '' ?>>
          <summary class="btn btn-secondary"><?= te('Manage session') ?><span class="active-parking__chevron" aria-hidden="true"></span></summary>
          <div class="active-parking__controls">
            <?php if ($showActionError): ?>
              <p class="active-parking__error" role="alert" tabindex="-1" data-parking-action-error><?= te($managementMessage) ?></p>
            <?php endif; ?>
            <div class="active-parking__control-row">
              <?php if ($canCancel): ?>
                <form method="post" action="dashboard.php#active-parking-title">
                  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="manage_session" value="1">
                  <input type="hidden" name="id" value="<?= e($session['id']) ?>">
                  <button type="submit" name="action" value="cancel" class="active-parking__cancel"
                          data-cancel-deadline="<?= e($deadline) ?>" aria-describedby="<?= e($cancelHintId) ?>"
                          data-confirm="<?= te('Cancel parking for {plate}?', ['plate' => $session['plate']]) ?>"><?= te('Cancel session') ?></button>
                </form>
              <?php endif; ?>
              <?php if ($end !== null && !$needsApproval && !parking_requires_new_booking($session, $destination)): ?>
                <form method="post" action="dashboard.php#active-parking-title">
                  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="manage_session" value="1">
                  <input type="hidden" name="id" value="<?= e($session['id']) ?>">
                  <input type="hidden" name="bay_type" value="<?= e($destination) ?>">
                  <button type="submit" name="action" value="change_parking_bay" class="btn btn-secondary"><?= te($destination === 'oku' ? 'Switch to OKU parking' : 'Switch to regular parking') ?></button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($end !== null && $needsApproval): ?>
              <p class="active-parking__deadline"><?= te('Approval is required to book OKU parking.') ?>
                <a href="oku-verification.php"><?= te('OKU Parking Verification') ?></a>
              </p>
            <?php elseif ($end !== null && parking_requires_new_booking($session, $destination)): ?>
              <p class="active-parking__deadline"><?= te(PARKING_REGULAR_NEW_BOOKING) ?></p>
            <?php endif; ?>
          </div>
        </details>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="active-parking__empty" data-parking-empty <?= $parkingCount ? 'hidden' : '' ?>>
    <div>
      <h3><?= te('No active parking') ?></h3>
      <p class="muted"><?= te('Choose a location to start parking.') ?></p>
    </div>
    <a class="btn" href="find_parking.php"><?= te('Find parking') ?> <span aria-hidden="true">→</span></a>
  </div>
  <div class="active-parking__more" data-parking-session-list <?= $parkingCount < 2 ? 'hidden' : '' ?>>
    <p class="muted" data-parking-session-count><?= te('{count} active sessions', ['count' => $parkingCount]) ?></p>
    <a class="btn btn-secondary" href="receipts.php?type=parking#parking-sessions"><?= te('View parking sessions') ?></a>
  </div>
  <p class="validation-sr-only" data-parking-announcement role="status"></p>
</section>
