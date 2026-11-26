<?php

require_once __DIR__ . '/parking-capacity.php';

/** Build a whitelist of lot metadata and aggregate bay counts, with no booking details. */
function parking_availability_catalog(array $lots, array $bookings): array {
    $occupiedByLot = parking_occupancy_by_lot($bookings);
    $locations = [];
    foreach ($lots as $lot) {
        $id = (int)$lot['id'];
        $capacity = parking_lot_capacity($lot);
        $bays = parking_bay_counts($lot, $occupiedByLot[$id] ?? []);
        $occupied = array_sum(array_column($bays, 'occupied'));
        $lat = filter_var($lot['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($lot['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $position = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180
            ? ['lat' => $lat, 'lng' => $lng] : null;
        $locations[] = [
            'id' => $id,
            'name' => (string)$lot['name'],
            'city' => (string)($lot['city'] ?? ''),
            'position' => $position,
            'capacity' => $capacity,
            'occupied' => $occupied,
            'available' => $capacity === null ? null : array_sum(array_column($bays, 'available')),
            'bays' => $bays,
        ];
    }
    return $locations;
}
