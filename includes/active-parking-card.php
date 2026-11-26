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
    <h2 id="active-parking-title"><?= te('Active parking') ?></h2>
  </div>
  <?php foreach ($parkingSessions as $index => ['booking' => $session, 'end' => $end, 'duration' => $duration]): ?>
    <?php
      $remaining = $end === null ? null : $end - $parkingNow;
      $endingSoon = $remaining !== null && $remaining <= PARKING_REMINDER_SECONDS;
      $endDate = $end === null || date('Y-m-d', $end) === date('Y-m-d', $parkingNow) ? '' : date('Y-m-d', $end);
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
