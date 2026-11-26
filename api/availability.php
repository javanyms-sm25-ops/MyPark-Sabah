<?php

require dirname(__DIR__) . '/includes/availability-snapshot.php';
require_once dirname(__DIR__) . '/includes/parking-availability.php';
require_once dirname(__DIR__) . '/includes/parking-pricing.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    echo json_encode(['error' => 'Only retrieval is available on this endpoint.']);
    exit;
}

$view = $_GET['view'] ?? '';
if (!in_array($view, ['', 'counts', 'dashboard'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose a valid availability view.']);
    exit;
}

try {
    $snapshot = mypark_availability_snapshot();
    if ($view === 'dashboard' && !in_array($snapshot['role'], ['admin', 'superadmin'], true)) {
        throw new RuntimeException('You do not have permission to access the Dashboard.', 403);
    }
    $locations = parking_availability_catalog($snapshot['lots'], $snapshot['bookings']);
    $output = match ($view) {
        'counts' => [
            'checkedAt' => $snapshot['checkedAt'],
            'locations' => array_map(static fn($lot) => ['id' => $lot['id'], 'bays' => $lot['bays']], $locations),
        ],
        'dashboard' => ['locations' => $locations, 'refreshed_at' => $snapshot['checkedAt']],
        // Keep the flat response for the existing slot display.
        default => array_map(static fn($lot) => [
            'id' => $lot['id'],
            'name' => $lot['name'],
            'city' => $lot['city'],
            'rate' => PARKING_HOURLY_CENTS / 100,
            'capacity' => $lot['capacity'],
            'busy' => $lot['occupied'],
        ], $locations),
    };
    echo json_encode($output, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $code = $error->getCode();
    if ($error instanceof RuntimeException && !($error instanceof PDOException) && in_array($code, [401, 403], true)) {
        http_response_code($code);
        echo json_encode(['error' => $code === 401 ? 'Please sign in again.' : 'You do not have access to this availability view.']);
    } else {
        error_log('MyPark could not read the parking availability snapshot.');
        http_response_code(503);
        echo json_encode(['error' => 'Parking availability is temporarily unavailable.']);
    }
}
