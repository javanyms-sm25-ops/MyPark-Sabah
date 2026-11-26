<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login('../pages/login.php');
header('Content-Type: application/json');

$out = [];
foreach (active_parking_locations() as $lot) {
    $out[] = [
        'id' => $lot['id'],
        'name' => $lot['name'],
        'city' => $lot['city'],
        'rate' => PARKING_HOURLY_CENTS / 100,
        'capacity' => $lot['capacity'],
        'busy' => count(array_filter(MyParkData::$records['demo_bookings'], fn($booking) =>
            $booking['lot_id'] === $lot['id'] && $booking['status'] === 'active'
        )),
    ];
}
echo json_encode($out);
