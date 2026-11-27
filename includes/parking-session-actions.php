<a class="btn" href="ticket.php?id=<?= e($booking['id']) ?>"><?= te('Ticket') ?></a>
<?php if ($status === 'active' && can_cancel_own_parking()):
    $deadline = booking_cancellation_deadline($booking);
    $canCancel = $deadline !== null && time() < $deadline;
    $cancelHintId = 'parking-cancel-policy-' . $booking['id'];
?>
  <form method="post" action="<?= e($parkingUrl) ?>" data-active-parking-action>
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" value="<?= e($booking['id']) ?>">
    <button name="action" value="cancel" class="danger" data-cancel-deadline="<?= e($deadline ?? 0) ?>"
      aria-describedby="<?= e($cancelHintId) ?>"
      <?php if ($canCancel): ?>title="<?= te(PARKING_CANCELLATION_POLICY) ?>"<?php else: ?>disabled<?php endif; ?>><?= te('Cancel') ?></button>
  </form>
  <?php if (me()['role'] === 'user' && $expiry !== null): ?>
    <?php $destinationBay = $bayType === 'regular' ? 'oku' : 'regular'; ?>
    <?php if (parking_requires_new_booking($booking, $destinationBay)): ?>
      <p class="hint"><?= te(PARKING_REGULAR_NEW_BOOKING) ?></p>
    <?php else: ?>
      <form method="post" action="<?= e($parkingUrl) ?>" data-active-parking-action>
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($booking['id']) ?>">
        <input type="hidden" name="bay_type" value="<?= e($destinationBay) ?>">
        <button name="action" value="change_parking_bay" class="ghost"><?= te($destinationBay === 'oku' ? 'Switch to OKU parking' : 'Switch to regular parking') ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  <p id="<?= e($cancelHintId) ?>" class="parking-cancellation-rule<?= $canCancel ? ' validation-sr-only' : '' ?>" data-cancellation-policy
    aria-live="polite"><?= te($canCancel ? PARKING_CANCELLATION_POLICY : PARKING_CANCELLATION_CLOSED) ?></p>
<?php endif; ?>
