<?php

require_once __DIR__ . '/employee-attendance.php';

const ATTENDANCE_REPORT_METRICS = [
    'scheduled' => 'Total Scheduled Employees',
    'checked_in' => 'Employees Checked In',
    'attendance_rate' => 'Attendance Rate',
    'pending' => 'Pending Attendance Reviews',
    'on_time' => 'On-Time Check-Ins',
    'late' => 'Late Check-Ins',
    'missing' => 'Missing Check-Ins',
    'leave' => 'Employees on Approved Leave',
    'full' => 'Employees with Full Attendance',
    'outside' => 'Outside-Zone Check-Ins',
];

const ATTENDANCE_PERFORMANCE_FIELDS = [
    'scheduled_days' => 'Scheduled Working Days',
    'present_days' => 'Days Present',
    'on_time_days' => 'Days On Time',
    'late_days' => 'Days Late',
    'absent_days' => 'Days Absent',
    'leave_days' => 'Approved Leave Days',
    'hours' => 'Recorded Working Hours',
    'attendance_rate' => 'Attendance Rate',
    'punctuality_rate' => 'Punctuality Rate',
    'full' => 'Full Attendance Status',
];

const ATTENDANCE_LOCATION_FIELDS = [
    'name' => 'Parking location', 'shifts' => 'Scheduled shifts', 'present' => 'Present shifts',
    'missing' => 'Missing Check-Ins', 'pending' => 'Pending Attendance Reviews',
];

const ATTENDANCE_FULL_LABELS = [
    'full' => 'Full attendance', 'not_full' => 'Does not qualify', 'open' => 'Period in progress',
    'review' => 'Review needed', 'none' => 'No required shifts',
];

const ATTENDANCE_EXCEPTION_LABELS = [
    'late' => 'Late check-in', 'missing' => 'Missing check-in', 'missing_checkout' => 'Missing check-out',
    'outside' => 'Outside-zone GPS', 'uncertain' => 'Uncertain GPS', 'correction' => 'Correction request',
    'technical' => 'Camera or GPS issue', 'unverified' => 'Unverified check-in',
];

function attendance_report_policy(PDO $db): array {
    return attendance_query($db, 'SELECT require_checkout, excuse_holidays FROM attendance_report_policy WHERE scope = ?',
        [vehicle_account_scope()])->fetch() ?: ['require_checkout' => 1, 'excuse_holidays' => 1];
}

function attendance_report_holidays(PDO $db, string $from = '1900-01-01', string $to = '9998-12-31'): array {
    return attendance_query($db, 'SELECT holiday_date, name FROM attendance_report_holidays
        WHERE scope = ? AND holiday_date BETWEEN ? AND ? ORDER BY holiday_date',
        [vehicle_account_scope(), $from, $to])->fetchAll(PDO::FETCH_KEY_PAIR);
}

function attendance_save_report_policy(PDO $db, array $actor, array $fields): string {
    if ($actor['role'] !== 'superadmin') throw new RuntimeException('Only Super Admin can change reporting settings.', 403);
    $policy = [
        'require_checkout' => attendance_int($fields['require_checkout'] ?? '0', 0, 1),
        'excuse_holidays' => attendance_int($fields['excuse_holidays'] ?? '0', 0, 1),
    ];
    return attendance_transaction($db, function () use ($db, $actor, $policy) {
        $previous = attendance_report_policy($db);
        attendance_query($db, 'INSERT INTO attendance_report_policy (scope, require_checkout, excuse_holidays)
            VALUES (?, ?, ?) ON CONFLICT(scope) DO UPDATE SET
            require_checkout = excluded.require_checkout, excuse_holidays = excluded.excuse_holidays',
            [vehicle_account_scope(), ...array_values($policy)]);
        if ($previous !== $policy) attendance_audit($db, $actor, null, 'Reporting settings updated',
            $previous, $policy, 'Full attendance policy updated.');
        return 'Reporting settings saved.';
    });
}

function attendance_save_holiday(PDO $db, array $actor, array $fields): string {
    if ($actor['role'] !== 'superadmin') throw new RuntimeException('Only Super Admin can change reporting settings.', 403);
    $date = $fields['holiday_date'] ?? '';
    $name = $fields['holiday_name'] ?? '';
    $name = is_string($name) ? trim($name) : '';
    $remove = ($fields['action'] ?? '') === 'remove_holiday';
    if (!attendance_date($date) || $date < '1900-01-01' || $date > '9998-12-31'
        || (!$remove && preg_match('/\A.{1,100}\z/u', $name) !== 1)) {
        throw new RuntimeException('Enter a valid holiday date and name of up to 100 characters.', 422);
    }
    return attendance_transaction($db, function () use ($db, $actor, $date, $name, $remove) {
        $scope = vehicle_account_scope();
        $previous = attendance_report_holidays($db, $date, $date)[$date] ?? null;
        if ($remove) {
            attendance_query($db, 'DELETE FROM attendance_report_holidays WHERE scope = ? AND holiday_date = ?', [$scope, $date]);
        } else {
            attendance_query($db, 'INSERT INTO attendance_report_holidays (scope, holiday_date, name) VALUES (?, ?, ?)
                ON CONFLICT(scope, holiday_date) DO UPDATE SET name = excluded.name', [$scope, $date, $name]);
        }
        $after = $remove ? null : $name;
        if ($previous !== $after) attendance_audit($db, $actor, null, 'Reporting holiday updated',
            ['holiday_date' => $date, 'holiday_name' => $previous], ['holiday_date' => $date, 'holiday_name' => $after],
            $remove ? 'Public holiday removed.' : 'Public holiday saved.');
        return $remove ? 'Public holiday removed.' : 'Public holiday saved.';
    });
}

function attendance_report_staff(PDO $db, array $actor, array $staff): array {
    if ($actor['role'] === 'staff') return array_intersect_key($staff, [$actor['id'] => true]);
    if ($actor['role'] === 'superadmin') return $staff;
    [$access, $params] = attendance_visibility($actor);
    $ids = attendance_query($db, "SELECT DISTINCT s.staff_id FROM attendance_shifts s WHERE $access", $params)->fetchAll(PDO::FETCH_COLUMN);
    return array_intersect_key($staff, array_fill_keys($ids, true));
}

function attendance_report_fact(array $row, array $holidays, array $policy, int $now): array {
    [$in, $out] = attendance_times($row);
    $leave = attendance_leave($row);
    $holiday = $holidays[$row['shift_date']] ?? null;
    $required = !$leave && !($holiday !== null && $policy['excuse_holidays']);
    $finished = $row['ends_at'] <= $now;
    $open = $row['review_status'] === 'pending';
    // An approved correction or check-in evidence can verify an arrival. A rejected
    // correction alone does not invalidate a previously verified arrival.
    $verified = $in !== null && (
        $row['corrected_in'] !== null || $row['checkin_review'] === 'approved'
        || ($row['in_zone'] === 'within' && !$row['has_camera_exception'] && $row['checkin_review'] !== 'rejected')
    );
    $checkoutMissing = $required && $finished && $in !== null && $out === null;
    $punctuality = attendance_punctuality($row);
    $state = match (true) {
        $leave => 'leave',
        !$required => 'holiday',
        $row['awaiting_reply'] === 1 => 'clarification',
        $open => 'pending',
        $in === null => $finished ? 'missing' : 'scheduled',
        !$verified => 'unverified',
        default => $punctuality,
    };
    $exceptions = array_keys(array_filter([
        'late' => $required && $punctuality === 'late',
        'missing' => $state === 'missing',
        'missing_checkout' => $checkoutMissing,
        'outside' => in_array('outside', [$row['in_zone'], $row['out_zone']], true),
        'uncertain' => in_array('uncertain', [$row['in_zone'], $row['out_zone']], true),
        'correction' => (bool)$row['has_correction'],
        'technical' => $row['has_camera_exception']
            || (bool)array_intersect(['denied', 'failed', 'skipped'], [$row['in_capture'], $row['out_capture']]),
        'unverified' => $state === 'unverified',
    ]));
    return $row + [
        'in' => $in, 'out' => $out, 'leave' => $leave, 'holiday_name' => $holiday, 'required' => $required,
        'finished' => $finished, 'open' => $open, 'verified' => $verified, 'punctuality' => $punctuality,
        'state' => $state, 'exceptions' => $exceptions, 'checkout_missing' => $checkoutMissing,
        'seconds' => $in !== null && $out !== null ? max(0, $out - $in) : 0,
    ];
}

function attendance_report_day(array $shifts): array {
    $required = array_filter($shifts, static fn($row) => $row['required']);
    $states = array_column($required, 'state');
    $unfinished = in_array(false, array_column($required, 'finished'), true);
    $unsettled = (bool)array_intersect(['unverified', 'pending', 'clarification'], $states);
    $state = 'rest';
    foreach (['missing', 'clarification', 'pending', 'unverified', 'scheduled', 'late', 'on_time'] as $key) {
        if (in_array($key, $states, true)) {
            $state = $key;
            break;
        }
    }
    if (!$required && $shifts) $state = in_array(true, array_column($shifts, 'leave'), true) ? 'leave' : 'holiday';
    // A live shift can show that the employee has arrived. The day settles only
    // when all the shifts scheduled for that employee and date have ended.
    $resolved = !$unfinished && (in_array($state, ['missing', 'on_time', 'late'], true));
    return [
        'state' => $state,
        'required' => (bool)$required,
        'present' => $resolved && in_array($state, ['on_time', 'late'], true),
        'absent' => $resolved && $state === 'missing',
        'unfinished' => $unfinished,
        'unsettled' => $unsettled,
        'missing_checkout' => in_array(true, array_column($required, 'checkout_missing'), true),
        'leave' => in_array(true, array_column($shifts, 'leave'), true),
    ];
}

function attendance_report_rate(int $count, int $total): ?float {
    return $total ? round(100 * $count / $total, 1) : null;
}

function attendance_employee_performance(array $days, array $policy, bool $periodClosed): array {
    $result = array_fill_keys(['scheduled_days', 'present_days', 'on_time_days', 'late_days', 'absent_days', 'leave_days'], 0);
    $seconds = 0;
    $unfinished = $unsettled = $missingCheckout = false;
    foreach ($days as $shifts) {
        $day = attendance_report_day($shifts);
        $result['scheduled_days'] += (int)$day['required'];
        $result['present_days'] += (int)$day['present'];
        $result['on_time_days'] += (int)($day['present'] && $day['state'] === 'on_time');
        $result['late_days'] += (int)($day['present'] && $day['state'] === 'late');
        $result['absent_days'] += (int)$day['absent'];
        $result['leave_days'] += (int)$day['leave'];
        $seconds += array_sum(array_column($shifts, 'seconds'));
        $unfinished = $unfinished || $day['unfinished'];
        $unsettled = $unsettled || $day['unsettled'];
        $missingCheckout = $missingCheckout || $day['missing_checkout'];
    }
    return $result + [
        'hours' => round($seconds / 3600, 2),
        'attendance_rate' => attendance_report_rate($result['present_days'], $result['present_days'] + $result['absent_days']),
        'punctuality_rate' => attendance_report_rate($result['on_time_days'], $result['present_days']),
        'full' => match (true) {
            !$result['scheduled_days'] => 'none',
            !$periodClosed || $unfinished => 'open',
            $unsettled => 'review',
            $result['absent_days'] > 0 || ($policy['require_checkout'] && $missingCheckout) => 'not_full',
            default => 'full',
        },
    ];
}

function attendance_employee_days(array $rows): array {
    $days = [];
    foreach ($rows as $row) $days[$row['staff_id']][$row['shift_date']][] = $row;
    return $days;
}

function attendance_report_metrics(array $rows, array $policy, bool $periodClosed, array $eligibility): array {
    $counts = array_fill_keys(array_keys(ATTENDANCE_REPORT_METRICS), 0);
    $checkedIn = $statuses = $locations = [];
    foreach ($rows as $row) {
        if ($row['in'] !== null) $checkedIn[$row['staff_id']] = true;
        if ($row['verified'] && !$row['open'] && $row['required']) $counts[$row['punctuality']]++;
        $counts['missing'] += (int)($row['state'] === 'missing');
        $counts['outside'] += (int)($row['in_zone'] === 'outside');
        $counts['pending'] += (int)$row['open'];
        $state = $row['state'];
        $statuses[$state] = ($statuses[$state] ?? 0) + 1;

        $lot = $row['lot_id'];
        $locations[$lot] ??= ['name' => $row['lot_name'], 'shifts' => 0, 'present' => 0, 'missing' => 0, 'pending' => 0];
        $locations[$lot]['shifts']++;
        $locations[$lot]['present'] += (int)($row['required'] && $row['finished'] && $row['verified'] && !$row['open']);
        $locations[$lot]['missing'] += (int)($state === 'missing');
        $locations[$lot]['pending'] += (int)$row['open'];
    }
    $counts['checked_in'] = count($checkedIn);

    $people = $daily = $monthly = [];
    $present = $absent = 0;
    foreach (attendance_employee_days($rows) as $id => $days) {
        $person = attendance_employee_performance($days, $policy, $periodClosed);
        $person['full'] = $eligibility[$id] ?? $person['full'];
        $people[$id] = $person;
        $counts['leave'] += (int)($person['leave_days'] > 0);
        $counts['full'] += (int)($person['full'] === 'full');
        $present += $person['present_days'];
        $absent += $person['absent_days'];
        foreach ($days as $date => $shifts) {
            $day = attendance_report_day($shifts);
            $month = substr($date, 0, 7);
            $daily[$date] ??= ['present' => 0, 'absent' => 0];
            $monthly[$month] ??= ['present' => 0, 'absent' => 0];
            $daily[$date]['present'] += (int)$day['present'];
            $daily[$date]['absent'] += (int)$day['absent'];
            $monthly[$month]['present'] += (int)$day['present'];
            $monthly[$month]['absent'] += (int)$day['absent'];
        }
    }
    $counts['scheduled'] = count($people);
    $counts['attendance_rate'] = attendance_report_rate($present, $present + $absent);
    ksort($daily);
    ksort($monthly);
    uasort($locations, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return ['summary' => $counts, 'people' => $people, 'daily' => $daily,
        'monthly' => $monthly, 'statuses' => $statuses, 'locations' => $locations];
}

function attendance_report_links(array $filters, string $page, array $overrides = []): string {
    $query = array_filter(array_replace($filters, $overrides), static fn($value) => $value !== '');
    return $page . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}
