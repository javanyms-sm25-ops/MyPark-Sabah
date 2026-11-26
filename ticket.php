<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$user = me();
$ticketId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$bookingKey = null;
foreach (MyParkData::$records['demo_bookings'] as $key => $booking) {
    if ($booking['id'] === $ticketId && ($booking['user_id'] === $user['id']
        || in_array($user['role'], ['staff', 'admin', 'superadmin'], true))) {
        $bookingKey = $key;
    }
}
if ($bookingKey === null) {
    http_response_code(404);
    exit('Ticket not found');
}

$booking = MyParkData::$records['demo_bookings'][$bookingKey];
$receipt = null;
if ($booking['user_id'] === $user['id']) {
    require_once __DIR__ . '/includes/receipt-data.php';
    receipt_sync();
    $receipt = receipt_for_owner($user['id'], MyParkData::$records['demo_bookings'][$bookingKey]['receipt_id'] ?? null);
}

header('Cache-Control: private, no-store');
head('Parking e-ticket');
?>
<div class="card ticket">
  <p class="eyebrow">MyPark SABAH</p>
  <h1>Parking e-ticket #<?= e($booking['id']) ?></h1>
  <div id="qr" data-text="<?= e('MyPark-SABAH|' . $booking['id'] . '|' . $booking['plate'] . '|' . $booking['lot']) ?>"></div>
  <p><b>Location:</b> <?= e($booking['lot']) ?>, <?= e($booking['city']) ?></p>
  <p><b>Vehicle:</b> <?= e($booking['plate']) ?></p>
  <p><b>Member:</b> <?= e($booking['user']) ?></p>
  <p><b>Duration:</b> <?= e($booking['hours']) ?> hour(s) · <?= e($booking['created_at']) ?></p>
  <p><b>Fee:</b> RM <?= number_format($booking['fee'], 2) ?> · <?= e($booking['status']) ?></p>
  <div class="ticket-actions">
    <?php if ($receipt): ?>
      <a class="btn" href="<?= e(receipt_url($receipt)) ?>"><?= te('View receipt') ?></a>
    <?php endif; ?>
    <button type="button"<?= $receipt ? ' class="ghost"' : '' ?> onclick="window.print()"><?= te('Print / Save PDF') ?></button>
  </div>
  <p class="hint">Prototype QR ticket; entry validation will be connected to backend later.</p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php foot(); ?>
