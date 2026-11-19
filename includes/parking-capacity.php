<?php

const PARKING_BAY_TYPES = ['regular' => 'Regular parking', 'oku' => 'OKU parking'];

function parking_lot_capacity(array $lot): ?int {
    $capacity = filter_var($lot['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $capacity === false ? null : $capacity;
}

function parking_booking_bay_type(array $booking): string {
    return ($booking['bay_type'] ?? '') === 'oku' ? 'oku' : 'regular';
}

function parking_occupancy_by_lot(array $bookings): array {
    $occupied = [];
    foreach ($bookings as $booking) {
        if ($booking['status'] !== 'active') continue;
        $lotId = $booking['lot_id'];
        $type = parking_booking_bay_type($booking);
        $occupied[$lotId][$type] = ($occupied[$lotId][$type] ?? 0) + 1;
    }
    return $occupied;
}

function parking_bay_counts(array $lot, array $occupied): array {
    $total = parking_lot_capacity($lot);
    $configuredOku = filter_var($lot['oku_capacity'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $reserved = $total === null ? null : min($total, $configuredOku === false ? 0 : $configuredOku);
    $capacity = ['regular' => $total === null ? null : $total - $reserved, 'oku' => $reserved];
    $bays = [];
    foreach ($capacity as $type => $count) {
        $used = $occupied[$type] ?? 0;
        $bays[$type] = [
            'capacity' => $count,
            'occupied' => $used,
            'available' => $count === null ? null : max(0, $count - $used),
        ];
    }
    return $bays;
}
