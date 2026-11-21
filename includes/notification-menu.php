<?php
$notices = parking_notification_items($user['id']);
$unread = count(array_filter($notices, fn($notice) => !$notice['read']));
?>
<details class="notification-menu" data-notification-menu data-user="<?= e($user['id']) ?>" data-csrf="<?= e(csrf()) ?>"
  data-zones="<?= e(json_encode(array_values(parking_alert_zones()), JSON_THROW_ON_ERROR)) ?>">
  <summary class="notification-trigger" aria-label="<?= $unread ? te('Parking notifications ({count} unread)', ['count' => $unread]) : te('Parking notifications') ?>">
    <span aria-hidden="true">🔔</span>
    <span class="notification-badge" aria-hidden="true" <?= $unread ? '' : 'hidden' ?>></span>
  </summary>
  <div class="notification-preview" aria-labelledby="notification-preview-heading">
    <h2 id="notification-preview-heading"><?= te('Latest notifications') ?></h2>
    <div data-notification-preview><?= parking_notice_list(array_slice($notices, 0, 3)) ?></div>
    <a class="notification-more" href="notifications.php"><?= te('View all notifications') ?> <span aria-hidden="true">→</span></a>
  </div>
</details>
