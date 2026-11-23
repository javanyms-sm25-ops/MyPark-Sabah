<?php

function reserve_email_delivery(string $deliveryKey, ?string $cooldownMessage = null): void {
    $now = time();
    $delivery = $_SESSION[$deliveryKey] ?? ['started' => $now, 'last' => 0, 'count' => 0];
    if ($cooldownMessage !== null && $now - $delivery['last'] < 60) {
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
