<?php

require_once __DIR__ . '/vehicles.php';

/** Only carry location and duration in links between Profile and the booking form. */
function parking_resume_fields(array $query): array {
    $fields = [];
    $lotId = filter_var($query['lot_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $hours = filter_var($query['hours'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    if ($lotId !== false) $fields['lot_id'] = $lotId;
    if ($hours !== false) $fields['hours'] = $hours;
    return $fields;
}

function parking_draft_from_post(array $input): array {
    return [
        'lot_id' => $input['lot_id'] ?? '',
        'vehicle_id' => $input['vehicle_id'] ?? '',
        'plate' => trim($input['plate'] ?? ''),
        'hours' => $input['hours'] ?? '',
        'save_vehicle' => ($input['save_vehicle'] ?? '') === '1',
    ];
}

/** Rebuild the details from server-side records for both review and confirmation. */
function validate_parking_draft(array $actor, array $draft): array {
    complete_expired_bookings();
    $errors = [];
    $lotId = filter_var($draft['lot_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $lots = array_column($_SESSION['demo_lots'], null, 'id');
    $lot = $lots[$lotId] ?? null;
    if ($lot === null) {
        $errors['location'] = 'Select a parking location.';
    } else {
        $occupied = 0;
        foreach ($_SESSION['demo_bookings'] as $booking) {
            if ($booking['lot_id'] === $lotId && $booking['status'] === 'active') $occupied++;
        }
        if ($occupied >= $lot['capacity']) {
            $errors['location'] = 'This parking location is full. Choose another location.';
        }
    }

    $hours = filter_var($draft['hours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    if ($hours === false) $errors['hours'] = 'Choose a whole number from 1 to 24 hours.';

    $manual = $draft['vehicle_id'] === 'manual';
    $vehicles = $actor['role'] === 'user' ? saved_vehicles($actor['id']) : [];
    $plate = null;
    if ($manual) {
        $plate = normalize_vehicle_plate($draft['plate']);
        if ($plate === null) $errors['plate'] = 'Enter a valid plate number, for example SAB 1234.';
    } else {
        $selected = $vehicles[$draft['vehicle_id']] ?? null;
        $plate = $selected === null ? null : normalize_vehicle_plate($selected['plate']);
        if ($plate === null) $errors['vehicle'] = 'Choose a saved vehicle from your account, or use another vehicle.';
    }

    $savePlate = $manual && $draft['save_vehicle'] && $actor['role'] === 'user';
    if ($savePlate && $plate !== null) {
        foreach ($vehicles as $vehicle) {
            if (normalize_vehicle_plate($vehicle['plate']) === $plate) {
                $savePlate = false;
                break;
            }
        }
    }

    return [
        'errors' => $errors,
        'details' => $errors ? null : [
            'lot' => $lot,
            'plate' => $plate,
            'hours' => $hours,
            'fee' => round($hours * $lot['rate'], 2),
            'save_vehicle' => $savePlate,
        ],
    ];
}

function start_parking(array $actor, array $details): void {
    $start = time();
    $bookingId = max(array_column($_SESSION['demo_bookings'], 'id') ?: [0]) + 1;
    if ($details['save_vehicle']) {
        change_saved_vehicle($actor['id'], ['vehicle_action' => 'add', 'plate' => $details['plate']]);
    }
    $_SESSION['demo_bookings'][] = [
        'id' => $bookingId,
        'user_id' => $actor['id'],
        'user' => $actor['name'],
        'lot_id' => $details['lot']['id'],
        'lot' => $details['lot']['name'],
        'city' => $details['lot']['city'],
        'slot' => 'P' . str_pad((string)$bookingId, 2, '0', STR_PAD_LEFT),
        'plate' => $details['plate'],
        'hours' => $details['hours'],
        'fee' => $details['fee'],
        'status' => 'active',
        'created_at' => date('Y-m-d H:i', $start),
        'expires_at' => $start + $details['hours'] * 3600,
    ];
    $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
}
