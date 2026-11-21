<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (me()['role'] === 'staff') {
    http_response_code(403);
    exit(te('Forbidden'));
}
header('Cache-Control: private, no-store');

$notifications = parking_notification_items(me()['id']);
head('Notifications');
?>

<div class="notification-page">
  <h1><?= te('Notifications') ?></h1>
  <p class="muted"><?= te('Keep MyPark open for a reminder when your parking session has five minutes left.') ?></p>
  <section class="card notification-settings" aria-labelledby="paid-alert-heading">
    <h2 id="paid-alert-heading"><?= te('Paid parking alerts') ?></h2>
    <p><?= te('Enable location alerts for mapped paid parking areas. Alerts run while this MyPark tab is visible.') ?></p>
    <button type="button" class="ghost" data-location-alert-toggle hidden><?= te('Enable location alerts') ?></button>
    <p class="hint" data-location-alert-status role="status" hidden></p>
  </section>
  <section class="card notification-inbox" aria-labelledby="notification-history-heading">
    <h2 id="notification-history-heading"><?= te('Notification history') ?></h2>
    <div data-notification-inbox><?= parking_notice_list($notifications, true) ?></div>
  </section>
</div>

<?php foot(); ?>
