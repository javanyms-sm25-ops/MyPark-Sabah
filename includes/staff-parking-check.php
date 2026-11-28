<?php

const STAFF_FINE_REASONS = [
    'no_active_session' => 'No active parking session',
    'wrong_bay' => 'Parked in the wrong bay',
    'obstruction' => 'Blocking access',
    'other' => 'Other parking issue',
];

const STAFF_PARKING_CHECK_LABELS = [
    'paid' => 'Active paid session found',
    'oku' => 'Active free OKU session found',
    'active_unconfirmed' => 'Active session found. Payment could not be confirmed.',
    'unverifiable' => 'A parking record could not be verified.',
    'no_active_session' => 'No active MyPark session found at this location',
];

function staff_parking_check(mixed $plate, mixed $lotId, int $now): array {
    $plate = is_string($plate) ? strtoupper(trim($plate)) : '';
    $key = staff_plate_key($plate);
    $lotId = filter_var($lotId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $lot = array_column(active_parking_locations(), null, 'id')[$lotId] ?? null;
    $errors = [];
    if (!preg_match('/\A[A-Z0-9][A-Z0-9 -]{1,14}\z/', $plate) || strlen($key) < 2) {
        $errors['plate'] = 'Enter a valid vehicle plate.';
    }
    if (!$lot) $errors['lot_id'] = 'Select an existing parking location.';
    if ($errors) throw new StaffValidationException($errors);

    $match = $latest = null;
    $unverifiable = false;
    foreach (MyParkData::$records['demo_bookings'] as $booking) {
        if (staff_plate_key($booking['plate']) !== $key || (int)$booking['lot_id'] !== $lotId) continue;
        $start = strtotime($booking['created_at']);
        $end = booking_expiry($booking);
        if ($start === false || $end === null || $end <= $start) {
            if ($booking['status'] === 'active') $unverifiable = true;
            continue;
        }
        if ($start > $now) continue;
        $session = ['id' => $booking['id'], 'start' => $start, 'end' => $end, 'status' => $booking['status'],
            'paid_cents' => $booking['paid_amount_cents'] ?? null, 'bay_type' => parking_booking_bay_type($booking)];
        if ($latest === null || $start > $latest['start']) $latest = $session;
        if ($booking['status'] === 'active' && $now < $end && ($match === null || $start > $match['start'])) {
            $match = $session;
        }
    }

    $status = match (true) {
        ($match['paid_cents'] ?? 0) > 0 => 'paid',
        $match !== null && $match['bay_type'] === 'oku' && $match['paid_cents'] === 0 => 'oku',
        $match !== null => 'active_unconfirmed',
        $unverifiable => 'unverifiable',
        default => 'no_active_session',
    };

    return ['plate' => $plate, 'lot_id' => $lotId, 'location' => $lot['name'], 'checked_at' => $now,
        'status' => $status, 'session' => $match ?? $latest];
}

function staff_unpaid_fines(string $plate): array {
    $key = staff_plate_key($plate);
    $unpaid = [];
    foreach (MyParkData::$records['staff_fines'] ?? [] as $fine) {
        if ($fine['plate_key'] !== $key || $fine['status'] !== 'unpaid') continue;
        $unpaid[] = array_intersect_key($fine, array_flip(['id', 'code', 'violation', 'amount_cents', 'occurred_ts']));
    }
    usort($unpaid, static fn(array $a, array $b) => $b['occurred_ts'] <=> $a['occurred_ts'] ?: $b['id'] <=> $a['id']);
    return ['count' => count($unpaid), 'items' => array_slice($unpaid, 0, 5)];
}

function staff_new_fine_fields(array $input): array {
    $now = time();
    $evidence = staff_parking_check($input['plate'] ?? null, $input['lot_id'] ?? null, $now);
    $reason = $input['violation_reason'] ?? '';
    $errors = [];
    if (!isset(STAFF_FINE_REASONS[$reason])) {
        $errors['violation_reason'] = 'Choose a parking violation reason.';
    } elseif ($reason === 'no_active_session' && $evidence['status'] !== 'no_active_session') {
        $errors['violation_reason'] = 'A missing parking session cannot be confirmed. Check parking again.';
    }
    $observation = staff_text($input, 'observation', 'On-site observation', 500, $errors);
    if ($errors) throw new StaffValidationException($errors);

    $input['violation'] = STAFF_FINE_REASONS[$reason];
    $input['occurred_at'] = date('Y-m-d\TH:i', $now);
    $fields = staff_fine_fields($input);
    $fields['occurred_ts'] = $now;
    return $fields + ['violation_reason' => $reason, 'lot_id' => $evidence['lot_id'],
        'location' => $evidence['location'], 'observation' => $observation, 'parking_evidence' => $evidence];
}
