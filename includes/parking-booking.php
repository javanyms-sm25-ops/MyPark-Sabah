<?php

require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/parking-capacity.php';
require_once __DIR__ . '/oku-verification.php';

const PARKING_WALLET_INSUFFICIENT = 'Your wallet balance is too low. Top up your wallet and try again.';

/** Carry the booking choices that can be shared in links. */
function parking_resume_fields(array $query): array {
    $fields = [];
    $lotId = filter_var($query['lot_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $hours = filter_var($query['hours'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    if ($lotId !== false) $fields['lot_id'] = $lotId;
    if ($hours !== false) $fields['hours'] = $hours;
    $bayType = $query['bay_type'] ?? null;
    if (is_string($bayType) && isset(PARKING_BAY_TYPES[$bayType])) $fields['bay_type'] = $bayType;
    return $fields;
}

function parking_draft_from_post(array $input): array {
    return [
        'lot_id' => $input['lot_id'] ?? '',
        'bay_type' => $input['bay_type'] ?? 'regular',
        'vehicle_id' => $input['vehicle_id'] ?? '',
        'plate' => trim($input['plate'] ?? ''),
        'hours' => $input['hours'] ?? '',
        'save_vehicle' => ($input['save_vehicle'] ?? '') === '1',
    ];
}

function parking_bay_error(array $actor, ?array $lot, string $bayType): ?string {
    if (!isset(PARKING_BAY_TYPES[$bayType])) return 'Choose a valid parking bay type.';
    if ($bayType === 'oku' && ($actor['role'] !== 'user' || !oku_is_approved($actor['id']))) {
        return OKU_APPROVAL_REQUIRED;
    }
    if ($lot === null) return null;

    // Every stay begins immediately, so every active stay overlaps the requested start.
    $occupied = parking_occupancy_by_lot(MyParkData::$records['demo_bookings']);
    $bay = parking_bay_counts($lot, $occupied[$lot['id']] ?? [])[$bayType];
    if ($bay['capacity'] === null) return 'Capacity unavailable';
    if ($bay['available'] > 0) return null;
    return $bayType === 'oku'
        ? 'No OKU bays are available at this location. Choose another location.'
        : 'No regular bays are available at this location. Choose another location.';
}

/** Rebuild the details from server-side records for both review and confirmation. */
function validate_parking_draft(array $actor, array $draft): array {
    complete_expired_bookings();
    $errors = [];
    $lotId = filter_var($draft['lot_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $lots = array_column(active_parking_locations(), null, 'id');
    $lot = $lots[$lotId] ?? null;
    $bayType = $draft['bay_type'] ?? 'regular';
    if ($lot === null) {
        $errors['location'] = 'Select a parking location.';
    }
    $bayError = parking_bay_error($actor, $lot, $bayType);
    if ($bayError !== null) $errors['bay_type'] = $bayError;

    $hours = filter_var($draft['hours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    if ($hours === false) $errors['hours'] = 'Choose a whole number from 1 to 24 hours.';

    $manual = $actor['role'] !== 'user' && $draft['vehicle_id'] === 'manual';
    $vehicles = $actor['role'] === 'user' ? saved_vehicles($actor['id']) : [];
    $plate = null;
    if ($manual) {
        $plate = normalize_vehicle_plate($draft['plate']);
        if ($plate === null) $errors['plate'] = 'Enter a valid plate number, for example SAB 1234.';
    } else {
        $selected = $vehicles[$draft['vehicle_id']] ?? null;
        $plate = $selected === null ? null : normalize_vehicle_plate($selected['plate']);
        if ($plate === null) $errors['vehicle'] = 'Choose a saved vehicle from your account before reviewing parking.';
    }

    if ($plate !== null) {
        foreach (MyParkData::$records['demo_bookings'] as $booking) {
            if ($booking['status'] === 'active' && vehicle_plate_key($booking['plate']) === vehicle_plate_key($plate)) {
                $errors['vehicle'] = 'This vehicle already has an active parking session. End it before booking again.';
                break;
            }
        }
    }

    return [
        'errors' => $errors,
        'details' => $errors ? null : [
            'lot' => $lot,
            'bay_type' => $bayType,
            'plate' => $plate,
            'vehicle_id' => $manual ? null : $draft['vehicle_id'],
            'hours' => $hours,
            'fee' => parking_fee($hours, $bayType),
            'save_vehicle' => false,
        ],
    ];
}

function start_parking(array $actor, array $details): int {
    if (!can_use_customer_wallet($actor)) throw new RuntimeException(CUSTOMER_WALLET_REQUIRED, 403);

    // Check again at the write boundary, including callers that bypass the review page.
    $checked = validate_parking_draft($actor, [
        'lot_id' => (string)$details['lot']['id'],
        'bay_type' => $details['bay_type'],
        'vehicle_id' => $details['vehicle_id'] ?? 'manual',
        'plate' => $details['plate'],
        'hours' => (string)$details['hours'],
        'save_vehicle' => false,
    ]);
    if ($checked['errors']) throw new RuntimeException(reset($checked['errors']), 409);
    if ($checked['details']['plate'] !== $details['plate'] || $checked['details']['fee'] !== $details['fee']) {
        throw new RuntimeException('The fee or vehicle details changed. Review the updated details before starting.', 409);
    }
    $wallet =& demo_wallet();
    $balanceCents = (int) round($wallet['balance'] * 100);
    $feeCents = (int) round($checked['details']['fee'] * 100);
    if ($feeCents > $balanceCents) throw new RuntimeException(PARKING_WALLET_INSUFFICIENT, 409);

    $start = time();
    $bookingId = max(MyParkData::$records['last_booking_id'] ?? 0, max(array_column(MyParkData::$records['demo_bookings'], 'id') ?: [0])) + 1;
    MyParkData::$records['demo_bookings'][] = [
        'id' => $bookingId,
        'user_id' => $actor['id'],
        'user' => $actor['name'],
        'lot_id' => $details['lot']['id'],
        'bay_type' => $details['bay_type'],
        'lot' => $details['lot']['name'],
        'city' => $details['lot']['city'],
        'slot' => 'P' . str_pad((string)$bookingId, 2, '0', STR_PAD_LEFT),
        'plate' => $details['plate'],
        'vehicle_id' => $details['vehicle_id'] ?? null,
        'hours' => $details['hours'],
        'fee' => $details['fee'],
        'paid_amount_cents' => $feeCents,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i', $start),
        'expires_at' => $start + $details['hours'] * 3600,
    ];
    if ($feeCents > 0) {
        $wallet['balance'] = ($balanceCents - $feeCents) / 100;
        $wallet['transactions'][] = [
            'type' => 'parking', 'amount' => $feeCents / 100,
            'created_at' => date('Y-m-d H:i', $start), 'status' => 'paid',
            'booking_id' => $bookingId, 'location' => $details['lot']['name'],
        ];
        parking_payment_notice($actor['id'], $feeCents, $details['lot']['name']);
    }
    MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
    return $bookingId;
}

const PARKING_REGULAR_NEW_BOOKING = 'To use a regular bay, book a new paid session.';

function parking_requires_new_booking(array $booking, string $bayType): bool {
    $paidCents = $booking['paid_amount_cents'] ?? round((float)($booking['fee'] ?? 0) * 100);
    return $bayType === 'regular' && (int)$paidCents === 0;
}

function change_parking_bay(array $member, int $id, string $bayType): void {
    if ($member['role'] !== 'user') {
        throw new RuntimeException('Only members can change their parking bay type.', 403);
    }
    complete_expired_bookings();
    $lots = array_column(MyParkData::$records['demo_lots'], null, 'id');
    foreach (MyParkData::$records['demo_bookings'] as &$booking) {
        if ($booking['id'] !== $id || $booking['user_id'] !== $member['id']) continue;
        if ($booking['status'] !== 'active' || (booking_expiry($booking) ?? 0) <= time()) {
            throw new RuntimeException('Only active stays can change bay type.', 409);
        }
        if (parking_booking_bay_type($booking) === $bayType) {
            throw new RuntimeException('This stay already uses that bay type.', 409);
        }
        if (parking_requires_new_booking($booking, $bayType)) {
            throw new RuntimeException(PARKING_REGULAR_NEW_BOOKING, 409);
        }
        $lot = $lots[$booking['lot_id']] ?? null;
        if ($lot === null) throw new RuntimeException('Parking location unavailable.', 409);
        $error = parking_bay_error($member, $lot, $bayType);
        if ($error !== null) throw new RuntimeException($error, $error === OKU_APPROVAL_REQUIRED ? 403 : 422);

        // The location, original start, remaining time and fee stay the same.
        $booking['bay_type'] = $bayType;
        MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
        return;
    }
    unset($booking);
    throw new RuntimeException('Booking not found.', 404);
}
