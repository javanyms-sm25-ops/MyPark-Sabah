<?php
require 'includes.php';
require_login();

$userId = me()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    check_csrf();
    foreach ($_SESSION['demo_bookings'] as &$booking) {
        if ($booking['id'] === (int) ($_POST['id'] ?? 0) && $booking['user_id'] === $userId) {
            $booking['status'] = 'cancelled';
        }
    }
    unset($booking);
    flash('Booking cancelled.');
    header('Location: history.php');
    exit;
}

$bookings = array_values(array_filter($_SESSION['demo_bookings'], fn($booking) => $booking['user_id'] === $userId));
head('Parking History');
?>

<p class="eyebrow">RESERVATION RECORDS</p>
<h1>My parking history</h1>

<section class="row card">
  <input id="historySearch" placeholder="Search plate, location or status">
  <select id="statusFilter">
    <option value="">All statuses</option>
    <option value="active">Active</option>
    <option value="completed">Completed</option>
    <option value="cancelled">Cancelled</option>
  </select>
</section>

<div class="table-wrap">
  <table id="historyTable" data-paginated="true">
    <thead><tr><th>Location</th><th>Plate</th><th>Duration</th><th>Fee</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($bookings as $booking): ?>
        <tr data-status="<?= e($booking['status']) ?>" data-search="<?= e(strtolower($booking['plate'] . ' ' . $booking['lot'] . ' ' . $booking['status'])) ?>">
          <td><?= e($booking['lot']) ?></td>
          <td><?= e($booking['plate']) ?></td>
          <td><?= $booking['hours'] ?>h</td>
          <td>RM <?= number_format($booking['fee'], 2) ?></td>
          <td><span class="tag <?= e($booking['status']) ?>"><?= e($booking['status']) ?></span><?php if ($booking['status'] === 'active'): ?><span class="parking-timer" data-expires="<?= (int) ($booking['expires_at'] ?? (time() + ($booking['hours'] * 3600))) ?>">--:--:--</span><small class="timer-label">Time remaining</small><?php endif; ?></td>
          <td><?= e($booking['created_at']) ?></td>
          <td><a class="btn" href="ticket.php?id=<?= $booking['id'] ?>">Ticket</a><?php if ($booking['status'] === 'active'): ?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $booking['id'] ?>"><button name="action" value="cancel" class="danger">Cancel</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><tr><td colspan="7">No parking history yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
