<?php
$isParking = $booking !== null || $item['type'] === 'parking';
$title = $item ? receipt_title($item) : 'Parking booking';
$summary = $booking['plate'] ?? $item['details']['Vehicle plate'] ?? $item['details']['Compound number'] ?? '';
$amount = $item['amount_cents'] ?? (int)round($booking['fee'] * 100);
$status = $booking['status'] ?? '';
$expiry = $status === 'active' ? booking_expiry($booking) : null;
$bayType = $booking ? parking_booking_bay_type($booking) : null;
$duration = $item['details']['Duration'] ?? ($booking
    ? $booking['hours'] . ($booking['hours'] === 1 ? ' hour' : ' hours') : '—');
?>
<li class="card receipt-row"<?php if ($booking): ?> data-parking-session data-status="<?= e($status) ?>" data-plate="<?= e($summary) ?>"<?php endif; ?>>
  <div>
    <h3><?= te($title) ?><?= $summary !== '' ? ' · ' . e($summary) : '' ?></h3>
    <p><?php if ($at): ?><time datetime="<?= e(date(DATE_ATOM, $at)) ?>"><?= e(receipt_time($at)) ?></time><?php else: ?>—<?php endif; ?></p>
  </div>
  <p class="receipt-row-amount"><?= e(receipt_money($amount)) ?></p>
  <?php if ($item): ?>
    <a class="btn" href="<?= e($listUrl . '&id=' . $item['id']) ?>"><?= te('View receipt') ?><span class="receipt-sr-only"> <?= e($item['number']) ?></span></a>
  <?php endif; ?>
  <?php if ($isParking): ?>
    <div class="receipt-parking-info">
      <dl class="receipt-parking-details">
        <div>
          <dt><?= te('Location') ?></dt>
          <dd><?= e($booking['lot'] ?? $item['details']['Location'] ?? '—') ?>
            <?php if ($bayType !== null): ?><small><?= te(PARKING_BAY_TYPES[$bayType]) ?></small><?php endif; ?>
          </dd>
        </div>
        <div><dt><?= te('Duration') ?></dt><dd><?= e($duration) ?></dd></div>
        <?php if ($booking): ?>
          <div>
            <dt><?= te('Status') ?></dt>
            <dd>
              <span class="tag <?= e($status) ?>"><?= te($status) ?></span>
              <?php if ($expiry !== null): ?><span class="parking-timer" data-expires="<?= e($expiry) ?>">--:--:--</span><small class="timer-label"><?= te('Time remaining') ?></small><?php endif; ?>
            </dd>
          </div>
        <?php endif; ?>
      </dl>
      <?php if ($booking): ?>
        <div class="parking-session-actions"><?php require __DIR__ . '/parking-session-actions.php'; ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</li>
