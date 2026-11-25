<?php

require_once __DIR__ . '/employee-attendance.php';

function attendance_save_shift(PDO $db, array $actor, array $fields): string {
    $staffId = attendance_int($fields['staff_id'] ?? null);
    $employee = attendance_accounts()[$staffId] ?? null;
    $lot = attendance_locations($db, $actor)[attendance_int($fields['lot_id'] ?? null)] ?? null;
    if (!$lot || ($employee['role'] ?? null) !== 'staff' || $employee['blocked']) {
        throw new RuntimeException('Choose an active employee and a workplace you manage.', 422);
    }
    $lat = attendance_number($lot['lat'] ?? null, -90, 90);
    $lng = attendance_number($lot['lng'] ?? null, -180, 180);
    if ($lat === null || $lng === null) throw new RuntimeException('This workplace needs valid map coordinates.', 422);
    $day = $fields['shift_date'] ?? '';
    $start = $fields['start_time'] ?? '';
    $end = $fields['end_time'] ?? '';
    if (!attendance_date($day) || !attendance_time($start) || !attendance_time($end) || $start === $end) {
        throw new RuntimeException('Enter a shift date and different start and end times.', 422);
    }
    $from = strtotime("$day $start");
    $to = strtotime("$day $end");
    if ($to < $from) $to = strtotime('+1 day', $to);
    $scope = vehicle_account_scope();
    return attendance_transaction($db, function () use ($db, $scope, $actor, $staffId, $lot, $lat, $lng, $day, $from, $to) {
        if (!isset(attendance_locations($db, $actor)[$lot['id']])) {
            throw new RuntimeException('You do not have access to that parking location.', 403);
        }
        $previous = attendance_query($db, 'SELECT id, lot_id, starts_at, ends_at FROM attendance_shifts
            WHERE scope = ? AND staff_id = ? AND shift_date = ?', [$scope, $staffId, $day])->fetch();
        if ($previous) {
            attendance_row($db, $actor, $previous['id']);
            if (attendance_query($db, 'SELECT 1 FROM attendance_records WHERE shift_id = ?', [$previous['id']])->fetchColumn()) {
                throw new RuntimeException('Attendance is already recorded for this shift.', 409);
            }
        }
        if (attendance_query($db, 'SELECT 1 FROM attendance_shifts WHERE scope = ? AND staff_id = ?
            AND id <> ? AND starts_at < ? AND ends_at > ? LIMIT 1',
            [$scope, $staffId, $previous['id'] ?? 0, $to, $from])->fetchColumn()) {
            throw new RuntimeException('This employee already has an overlapping shift.', 409);
        }

        $values = [$lot['id'], $lot['name'], $lat, $lng, $from, $to];
        if ($previous) {
            attendance_query($db, 'UPDATE attendance_shifts SET lot_id = ?, lot_name = ?, lot_lat = ?, lot_lng = ?,
                starts_at = ?, ends_at = ? WHERE id = ?', [...$values, $previous['id']]);
            $shiftId = $previous['id'];
        } else {
            attendance_query($db, 'INSERT INTO attendance_shifts (lot_id, lot_name, lot_lat, lot_lng, starts_at, ends_at, scope, staff_id, shift_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$values, $scope, $staffId, $day]);
            $shiftId = (int)$db->lastInsertId();
        }
        attendance_audit($db, $actor, $shiftId, 'Shift scheduled', $previous ?: [],
            ['lot_id' => $lot['id'], 'starts_at' => $from, 'ends_at' => $to], 'Work schedule updated.');
        return 'Work shift saved.';
    });
}

function attendance_save_settings(PDO $db, array $actor, array $fields): string {
    if ($actor['role'] !== 'superadmin') throw new RuntimeException('Only Super Admin can change attendance settings.', 403);
    $updated = [
        'radius_m' => attendance_int($fields['radius_m'] ?? null, 25, 5000),
        'accuracy_m' => attendance_int($fields['accuracy_m'] ?? null, 5, 1000),
        'grace_minutes' => attendance_int($fields['grace_minutes'] ?? null, 0, 120),
    ];
    return attendance_transaction($db, function () use ($db, $actor, $updated) {
        $previous = attendance_settings($db);
        attendance_query($db, 'INSERT INTO attendance_settings (scope, radius_m, accuracy_m, grace_minutes) VALUES (?, ?, ?, ?)
            ON CONFLICT(scope) DO UPDATE SET radius_m = excluded.radius_m, accuracy_m = excluded.accuracy_m,
            grace_minutes = excluded.grace_minutes', [vehicle_account_scope(), ...array_values($updated)]);
        attendance_audit($db, $actor, null, 'Settings updated', $previous, $updated, 'Attendance policy updated.');
        return 'Attendance settings saved.';
    });
}

function attendance_set_scope(PDO $db, array $actor, array $fields): string {
    if ($actor['role'] !== 'superadmin') throw new RuntimeException('Only Super Admin can assign attendance access.', 403);
    $adminId = attendance_int($fields['admin_id'] ?? null);
    $lotId = attendance_int($fields['lot_id'] ?? null);
    $admin = attendance_accounts()[$adminId] ?? null;
    if (($admin['role'] ?? null) !== 'admin' || $admin['blocked'] || !isset(attendance_locations($db, $actor)[$lotId])) {
        throw new RuntimeException('Choose an active Admin and a parking location.', 422);
    }
    $grant = ($fields['action'] ?? '') === 'grant_scope';
    return attendance_transaction($db, function () use ($db, $actor, $adminId, $lotId, $grant) {
        $params = [vehicle_account_scope(), $adminId, $lotId];
        $existed = (bool)attendance_query($db, 'SELECT 1 FROM attendance_admin_locations WHERE scope = ? AND admin_id = ? AND lot_id = ?', $params)->fetchColumn();
        if ($existed !== $grant) {
            if ($grant) {
                attendance_query($db, 'INSERT INTO attendance_admin_locations (scope, admin_id, lot_id) VALUES (?, ?, ?)', $params);
            } else {
                attendance_query($db, 'DELETE FROM attendance_admin_locations WHERE scope = ? AND admin_id = ? AND lot_id = ?', $params);
            }
            $subject = ['admin_id' => $adminId, 'lot_id' => $lotId];
            attendance_audit($db, $actor, null, 'Admin scope updated', $subject + ['granted' => $existed],
                $subject + ['granted' => $grant], 'Attendance access updated.');
        }
        return $grant ? 'Admin attendance access granted.' : 'Admin attendance access removed.';
    });
}

function attendance_init_record(PDO $db, int $id, int $now, array $settings): void {
    attendance_query($db, 'INSERT INTO attendance_records (shift_id, radius_m, accuracy_m, grace_minutes, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(shift_id) DO NOTHING',
        [$id, $settings['radius_m'], $settings['accuracy_m'], $settings['grace_minutes'], $now, $now]);
}

function attendance_request(PDO $db, int $id, string $kind, string $reason, int $now, ?int $in = null, ?int $out = null): bool {
    return attendance_query($db, 'INSERT INTO attendance_requests (shift_id, kind, reason, proposed_in, proposed_out, created_at)
        VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(shift_id) WHERE status = \'pending\' DO NOTHING',
        [$id, $kind, $reason, $in, $out, $now])->rowCount() === 1;
}

function attendance_can_check_in(array $row, int $now): bool {
    return attendance_times($row)[0] === null && !attendance_leave($row)
        && $row['review_status'] !== 'pending'
        && $now >= $row['starts_at'] - 1800 && $now < $row['ends_at'];
}

function attendance_can_check_out(array $row, int $now): bool {
    [$in, $out] = attendance_times($row);
    return $in !== null && $out === null && $now >= $in && $now - $in <= 86400;
}

function attendance_capture(PDO $db, array $actor, int $id, string $action, array $fields, int $now): string {
    if ($actor['role'] !== 'staff') throw new RuntimeException('Only employees can record attendance.', 403);
    $checkOut = $action === 'check_out';
    $exception = $action === 'exception';
    return attendance_transaction($db, function () use ($db, $actor, $id, $checkOut, $exception, $fields, $now) {
        $row = attendance_row($db, $actor, $id);
        [$in, $out] = attendance_times($row);
        if ($checkOut && $out !== null) throw new RuntimeException('This shift is already checked out.', 409);
        if (!$checkOut && $in !== null) throw new RuntimeException('This shift is already checked in.', 409);
        if ($checkOut ? !attendance_can_check_out($row, $now) : !attendance_can_check_in($row, $now)) {
            throw new RuntimeException('This shift is outside its attendance window. You can request a correction.', 409);
        }
        $settings = $row['radius_m'] !== null ? $row : attendance_settings($db);
        $gps = attendance_gps($fields, $row, $settings);
        $photo = !$checkOut && !$exception ? attendance_photo($fields['photo'] ?? null) : null;
        $reason = $exception ? attendance_reason($fields['reason'] ?? null) : 'Location evidence requires review.';
        attendance_init_record($db, $id, $now, $settings);
        if ($checkOut) {
            attendance_query($db, 'UPDATE attendance_records SET checked_out = ?, out_lat = ?, out_lng = ?, out_accuracy = ?,
                out_distance = ?, out_capture = ?, out_zone = ?, updated_at = ? WHERE shift_id = ?', [$now, ...$gps, $now, $id]);
        } else {
            attendance_query($db, 'UPDATE attendance_records SET checked_in = ?, in_lat = ?, in_lng = ?, in_accuracy = ?,
                in_distance = ?, in_capture = ?, in_zone = ?, updated_at = ? WHERE shift_id = ?', [$now, ...$gps, $now, $id]);
        }
        if ($photo !== null) {
            $stmt = $db->prepare('INSERT INTO attendance_photos (shift_id, captured_at, jpeg) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            $stmt->bindValue(2, $now, PDO::PARAM_INT);
            $stmt->bindValue(3, $photo, PDO::PARAM_LOB);
            $stmt->execute();
        }
        if ($exception || $gps[5] !== 'within') {
            attendance_request($db, $id, $exception ? 'exception' : 'evidence', $reason, $now);
            return $checkOut ? 'Check-out recorded. Location evidence is pending review.' : 'Check-in recorded and sent for review.';
        }
        return $checkOut ? 'Check-out recorded.' : 'Check-in recorded.';
    });
}

function attendance_proposed_time(mixed $value, array $row, int $now): ?int {
    if ($value === null || $value === '') return null;
    if (!is_string($value) || !attendance_date(substr($value, 0, 10)) || substr($value, 10, 1) !== 'T'
        || !attendance_time(substr($value, 11))) {
        throw new RuntimeException('Enter a valid date and time in Malaysia time.', 422);
    }
    $time = strtotime($value);
    if ($time > $now || $time < $row['starts_at'] - 7200 || $time > $row['ends_at'] + 86400) {
        throw new RuntimeException('The proposed time must be near this shift and cannot be in the future.', 422);
    }
    return $time;
}

function attendance_validate_correction(array $row, ?int $proposedIn, ?int $proposedOut): void {
    [$in, $out] = attendance_times($row);
    $in = $proposedIn ?? $in;
    $out = $proposedOut ?? $out;
    if ($out !== null && ($in === null || $out <= $in || $out - $in > 86400)) {
        throw new RuntimeException('Check-out must follow check-in within 24 hours.', 422);
    }
}

function attendance_correct(PDO $db, array $actor, int $id, array $fields, int $now): string {
    if ($actor['role'] !== 'staff') throw new RuntimeException('Only employees can request a correction.', 403);
    return attendance_transaction($db, function () use ($db, $actor, $id, $fields, $now) {
        $row = attendance_row($db, $actor, $id);
        if ($now < $row['starts_at'] - 1800 || attendance_leave($row)) {
            throw new RuntimeException('This shift does not need an attendance correction.', 409);
        }
        $in = attendance_proposed_time($fields['proposed_in'] ?? null, $row, $now);
        $out = attendance_proposed_time($fields['proposed_out'] ?? null, $row, $now);
        if ($in === null && $out === null) throw new RuntimeException('Enter at least one proposed time.', 422);
        attendance_validate_correction($row, $in, $out);
        $reason = attendance_reason($fields['reason'] ?? null);
        attendance_init_record($db, $id, $now, attendance_settings($db));
        if (!attendance_request($db, $id, 'correction', $reason, $now, $in, $out)) {
            throw new RuntimeException('A review is already pending for this shift.', 409);
        }
        return 'Correction request sent for review.';
    });
}

function attendance_decide(PDO $db, array $actor, int $id, array $fields): string {
    if (!in_array($actor['role'], ['admin', 'superadmin'], true)) throw new RuntimeException('You cannot review attendance.', 403);
    $requestId = attendance_int($fields['request_id'] ?? null);
    $decision = $fields['decision'] ?? '';
    if (!in_array($decision, ['approved', 'rejected', 'clarification'], true)) throw new RuntimeException('Choose a review action.', 422);
    $reason = attendance_reason($fields['reason'] ?? null);
    return attendance_transaction($db, function () use ($db, $actor, $id, $requestId, $decision, $reason) {
        $row = attendance_row($db, $actor, $id);
        $request = attendance_query($db, 'SELECT * FROM attendance_requests WHERE id = ? AND shift_id = ?', [$requestId, $id])->fetch();
        if (!$request || $request['status'] !== 'pending') throw new RuntimeException('This request is no longer pending.', 409);
        [$in, $out] = attendance_times($row);
        $now = time();
        $before = ['status' => $row['awaiting_reply'] ? 'clarification' : 'pending', 'request_id' => $requestId,
            'check_in' => $in, 'check_out' => $out];
        if ($decision === 'clarification') {
            if ($request['clarified_at'] !== null) throw new RuntimeException('Clarification has already been requested.', 409);
            attendance_query($db, 'UPDATE attendance_requests SET clarification_reason = ?, clarifier_id = ?, clarified_at = ? WHERE id = ?',
                [$reason, $actor['id'], $now, $requestId]);
            attendance_audit($db, $actor, $id, 'Attendance review', $before, array_replace($before, ['status' => 'clarification']), $reason);
            return 'Clarification requested from the employee.';
        }
        if ($decision === 'approved' && $request['kind'] === 'correction') {
            attendance_validate_correction($row, $request['proposed_in'], $request['proposed_out']);
            $in = $request['proposed_in'] ?? $in;
            $out = $request['proposed_out'] ?? $out;
            attendance_query($db, 'UPDATE attendance_records SET corrected_in = ?, corrected_out = ?, updated_at = ? WHERE shift_id = ?',
                [$request['proposed_in'] ?? $row['corrected_in'], $request['proposed_out'] ?? $row['corrected_out'], $now, $id]);
        }
        attendance_query($db, 'UPDATE attendance_requests SET status = ?, reviewer_id = ?, reviewed_at = ?, decision_reason = ? WHERE id = ?',
            [$decision, $actor['id'], $now, $reason, $requestId]);
        attendance_audit($db, $actor, $id, 'Attendance review', $before,
            ['status' => $decision, 'request_id' => $requestId, 'check_in' => $in, 'check_out' => $out], $reason);
        return $decision === 'approved' ? 'Attendance review approved.' : 'Attendance review rejected.';
    });
}

function attendance_reply(PDO $db, array $actor, int $shiftId, array $fields, int $now): string {
    if ($actor['role'] !== 'staff') throw new RuntimeException('Only the employee can reply to this review.', 403);
    $id = attendance_int($fields['request_id'] ?? null);
    $message = attendance_reason($fields['reason'] ?? null);
    return attendance_transaction($db, function () use ($db, $actor, $shiftId, $id, $message, $now) {
        attendance_row($db, $actor, $shiftId);
        $updated = attendance_query($db, "UPDATE attendance_requests SET clarification_reply = ?, replied_at = ?
            WHERE id = ? AND shift_id = ? AND status = 'pending' AND clarified_at IS NOT NULL AND replied_at IS NULL",
            [$message, $now, $id, $shiftId]);
        if (!$updated->rowCount()) throw new RuntimeException('This review is no longer waiting for a reply.', 409);
        return 'Your reply was sent for review.';
    });
}
