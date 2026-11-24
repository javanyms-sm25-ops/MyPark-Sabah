<?php

const PARKING_REMINDER_SECONDS = 300;
// The three demo locations have known map coordinates. Newly added lots use a placeholder.
const PARKING_ALERT_RADII = [1 => 150, 2 => 150, 3 => 150];

function parking_add_notice(int $userId, array $notice): void {
    $id = bin2hex(random_bytes(12));
    $items = $_SESSION['parking_notifications'][$userId] ?? [];
    $items[$id] = $notice + ['id' => $id, 'created_at' => time(), 'read' => false];
    $_SESSION['parking_notifications'][$userId] = array_slice($items, -60, null, true);
}

function parking_notification_items(int $userId): array {
    $now = time();
    foreach ($_SESSION['demo_bookings'] as &$booking) {
        if ($booking['user_id'] !== $userId || $booking['status'] !== 'active') continue;
        $expiry = booking_expiry($booking);
        if ($expiry === null || $expiry <= $now || $expiry - $now > PARKING_REMINDER_SECONDS ||
            ($booking['reminded_for_expiry'] ?? null) === $expiry) continue;

        parking_add_notice($userId, [
            'type' => 'ending', 'location' => $booking['lot'], 'plate' => $booking['plate'],
            'booking_id' => $booking['id'], 'expires_at' => $expiry,
        ]);
        $booking['reminded_for_expiry'] = $expiry;
    }
    unset($booking);

    return array_reverse($_SESSION['parking_notifications'][$userId] ?? [], true);
}

function parking_alert_zones(): array {
    $zones = [];
    foreach ($_SESSION['demo_lots'] as $lot) {
        $id = (int)$lot['id'];
        $radius = PARKING_ALERT_RADII[$id] ?? null;
        $lat = filter_var($lot['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($lot['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($radius === null || $lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) continue;
        $zones[$id] = ['id' => $id, 'lat' => $lat, 'lng' => $lng, 'radius' => $radius];
    }
    return $zones;
}

function parking_zone_notice(int $userId, int $zoneId): void {
    $now = time();
    // A reload or concurrent tab should not create repeated alerts for the same location.
    if ($now - ($_SESSION['parking_zone_notice_at'][$userId][$zoneId] ?? 0) < 600) return;

    $lots = array_column($_SESSION['demo_lots'], null, 'id');
    $lot = $lots[$zoneId];
    parking_add_notice($userId, [
        'type' => 'zone', 'location' => $lot['name'], 'lot_id' => $zoneId,
    ]);
    $_SESSION['parking_zone_notice_at'][$userId][$zoneId] = $now;
}

function parking_payment_notice(int $userId, int $amountCents, ?string $location = null): void {
    parking_add_notice($userId, [
        'type' => 'payment', 'amount_cents' => $amountCents, 'location' => $location,
    ]);
}

function parking_notice_title(array $notice): string {
    return t(match ($notice['type']) {
        'ending' => 'Parking ends soon',
        'cancelled' => 'Parking cancelled',
        'zone' => 'Paid parking nearby',
        'payment' => 'Payment successful',
    });
}

function parking_notice_message(array $notice): string {
    return match ($notice['type']) {
        'ending' => t('Parking for {plate} at {location} ends at {time} MYT.', [
            'plate' => $notice['plate'], 'location' => $notice['location'], 'time' => date('H:i', $notice['expires_at']),
        ]),
        'cancelled' => t('Parking for {plate} at {location} was cancelled.', [
            'plate' => $notice['plate'], 'location' => $notice['location'],
        ]),
        'zone' => t('You are near {location}. {price}', [
            'location' => $notice['location'], 'price' => parking_price_label(),
        ]),
        'payment' => t(match (true) {
            isset($notice['location']) => 'You paid RM {amount} for parking at {location}.',
            isset($notice['compound']) => 'You paid RM {amount} for compound {number}.',
            default => 'RM {amount} was added to your wallet.',
        }, [
            'amount' => number_format($notice['amount_cents'] / 100, 2),
            'location' => $notice['location'] ?? '', 'number' => $notice['compound'] ?? '',
        ]),
    };
}

function parking_notice_list(array $items, bool $showLinks = false): string {
    ob_start();
    if (!$items): ?>
      <p class="notification-empty"><?= te('No notifications yet.') ?></p>
    <?php else: ?>
      <ul class="notification-items">
        <?php foreach ($items as $notice): ?>
          <li class="notification-item" data-notice="<?= e($notice['id']) ?>" <?= $notice['read'] ? '' : 'data-unread' ?>>
            <div class="notification-item__heading">
              <strong><?= e(parking_notice_title($notice)) ?></strong>
              <time datetime="<?= e(date(DATE_ATOM, $notice['created_at'])) ?>"><?= e(date('j M, H:i', $notice['created_at'])) ?> MYT</time>
            </div>
            <p><?= e(parking_notice_message($notice)) ?></p>
            <?php if ($showLinks):
                [$url, $label] = match ($notice['type']) {
                    'ending', 'cancelled' => ['receipts.php?type=parking#parking-sessions', 'View parking sessions'],
                    'zone' => ['parking-details.php?lot_id=' . $notice['lot_id'], 'View parking details'],
                    'payment' => match (true) {
                        isset($notice['location']) => ['receipts.php?type=parking', 'View parking receipts'],
                        isset($notice['compound']) => ['receipts.php?type=compound', 'View compound receipts'],
                        default => ['receipts.php?type=topup', 'View wallet top-up receipts'],
                    },
                };
            ?>
              <a class="btn btn-secondary" href="<?= e($url) ?>"><?= te($label) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif;
    return ob_get_clean();
}
