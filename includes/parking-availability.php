<?php

require_once __DIR__ . '/parking-capacity.php';

/** A changed catalog needs a page refresh to show current location details. */
function parking_availability_catalog_version(array $lots): string {
    $catalog = array_map(static fn(array $lot): array => [
        $lot['id'], $lot['name'], $lot['city'] ?? '', $lot['address'] ?? '',
        $lot['lat'] ?? null, $lot['lng'] ?? null,
    ], $lots);
    return hash('sha256', json_encode($catalog, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
}

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
