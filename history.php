<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = me()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    check_csrf();
    flash(cancel_booking((int) ($_POST['id'] ?? 0), $userId));
    header('Location: history.php');
    exit;
}

$bookings = array_values(array_filter($_SESSION['demo_bookings'], fn($booking) => $booking['user_id'] === $userId));
head('Parking History');
?>

<p class="eyebrow"><?= te('RESERVATION RECORDS') ?></p>
<h1><?= te('My parking history') ?></h1>
<p><a class="btn" href="receipts.php?type=parking"><?= te('View parking receipts') ?></a></p>

<section class="row card">
  <input id="historySearch" placeholder="<?= te('Search plate, location or status') ?>">
  <select id="statusFilter">
    <option value=""><?= te('All statuses') ?></option>
    <option value="active"><?= te('Active') ?></option>
    <option value="completed"><?= te('Completed') ?></option>
    <option value="cancelled"><?= te('Cancelled') ?></option>
  </select>
</section>

<div class="table-wrap">
  <table id="historyTable" data-paginated="true">
    <thead><tr><th><?= te('Location') ?></th><th><?= te('Plate') ?></th><th><?= te('Duration') ?></th><th><?= te('Fee') ?></th><th><?= te('Status') ?></th><th><?= te('Created') ?></th><th><?= te('Actions') ?></th></tr></thead>
    <tbody>
      <?php foreach ($bookings as $booking): ?>
        <?php $expiry = $booking['status'] === 'active' ? booking_expiry($booking) : null; ?>
        <tr data-status="<?= e($booking['status']) ?>" data-search="<?= e(strtolower($booking['plate'] . ' ' . $booking['lot'] . ' ' . $booking['status'] . ' ' . t($booking['status']))) ?>">
          <td><?= e($booking['lot']) ?></td>
          <td><?= e($booking['plate']) ?></td>
          <td><?= $booking['hours'] ?>h</td>
          <td>RM <?= number_format($booking['fee'], 2) ?></td>
          <td><span class="tag <?= e($booking['status']) ?>"><?= te($booking['status']) ?></span><?php if ($expiry !== null): ?><span class="parking-timer" data-expires="<?= $expiry ?>">--:--:--</span><small class="timer-label"><?= te('Time remaining') ?></small><?php endif; ?></td>
          <td><?= e($booking['created_at']) ?></td>
          <td><a class="btn" href="ticket.php?id=<?= $booking['id'] ?>"><?= te('Ticket') ?></a><?php if ($booking['status'] === 'active'): ?><form method="post" class="inline" data-booking-cancel><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $booking['id'] ?>"><button name="action" value="cancel" class="danger"><?= te('Cancel') ?></button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><tr><td colspan="7"><?= te('No parking history yet.') ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
