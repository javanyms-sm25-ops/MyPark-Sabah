<a class="btn" href="ticket.php?id=<?= e($booking['id']) ?>"><?= te('Ticket') ?></a>
<?php if ($status === 'active'):
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
    <form method="post" action="<?= e($parkingUrl) ?>" data-active-parking-action>
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($booking['id']) ?>">
      <input type="hidden" name="bay_type" value="<?= $bayType === 'regular' ? 'oku' : 'regular' ?>">
      <button name="action" value="change_parking_bay" class="ghost"><?= te($bayType === 'regular' ? 'Switch to OKU parking' : 'Switch to regular parking') ?></button>
    </form>
  <?php endif; ?>
  <p id="<?= e($cancelHintId) ?>" class="parking-cancellation-rule<?= $canCancel ? ' validation-sr-only' : '' ?>" data-cancellation-policy
    aria-live="polite"><?= te($canCancel ? PARKING_CANCELLATION_POLICY : PARKING_CANCELLATION_CLOSED) ?></p>
<?php endif; ?>
