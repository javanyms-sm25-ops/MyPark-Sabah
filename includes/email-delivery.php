<?php

function email_delivery_wait(string $deliveryKey): int {
    return max(0, ($_SESSION[$deliveryKey]['last'] ?? 0) + 60 - time());
}

function reserve_email_delivery(string $deliveryKey, ?string $cooldownMessage = null): void {
    $now = time();
    $delivery = $_SESSION[$deliveryKey] ?? ['started' => $now, 'last' => 0, 'count' => 0];
    if ($cooldownMessage !== null && email_delivery_wait($deliveryKey) > 0) {
        throw new RuntimeException($cooldownMessage, 429);
    }
    if ($now - $delivery['started'] >= 900) {
        $delivery = ['started' => $now, 'last' => 0, 'count' => 0];
    }
    if ($delivery['count'] >= 5) {
        throw new RuntimeException('Too many verification emails requested. Please try again later.', 429);
    }
    $delivery['last'] = $now;
    $delivery['count']++;
    $_SESSION[$deliveryKey] = $delivery;
}
