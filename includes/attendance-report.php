<?php

require_once __DIR__ . '/attendance-analytics.php';

const ATTENDANCE_REPORT_TYPES = [
    'daily' => 'Daily Attendance Report', 'weekly' => 'Weekly Attendance Report',
    'monthly' => 'Monthly Attendance Summary', 'individual' => 'Individual Employee Attendance Report',
    'full' => 'Full Attendance Report', 'exceptions' => 'Late Attendance & Exception Report',
    'location' => 'Attendance by Parking Location', 'custom' => 'Custom Date Range Attendance Report',
];
const ATTENDANCE_REPORT_STATUSES = [
    'scheduled', 'on_time', 'late', 'checked_out', 'missing', 'missing_checkout', 'leave', 'holiday',
    'pending', 'clarification', 'unverified', 'rejected',
];
const ATTENDANCE_REPORT_FORMATS = ['pdf' => 'PDF', 'xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV'];
const ATTENDANCE_REPORT_MAX_ROWS = 10000;

function attendance_report_choice(mixed $value, array $choices, string $message): string {
    if (!is_string($value) || !array_key_exists($value, $choices)) throw new RuntimeException($message, 422);
    return $value;
}

function attendance_report_filters(array $query, array $locations, array $staff, int $now, string $type): array {
    $filters = [];
    foreach (['from', 'to', 'employee', 'location', 'status', 'search', 'exception'] as $field) {
        $value = $query[$field] ?? '';
        if (!is_string($value) || strlen($value) > 320 || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new RuntimeException('Invalid attendance filter.', 422);
        }
        $filters[$field] = trim($value);
    }
    ['from' => $from, 'to' => $to, 'employee' => $employee, 'location' => $location, 'status' => $status,
        'exception' => $exception] = $filters;
    if ($from === '' && $to === '') $from = $to = date('Y-m-d', $now);
    if (!attendance_date($from) || $from < '1900-01-01' || $from > '9998-12-31') {
        throw new RuntimeException('Choose a valid start date and end date.', 422);
    }
    $anchor = new DateTimeImmutable($from);
    [$from, $to] = match ($type) {
        'daily' => [$from, $from],
        'weekly' => [$anchor->modify('monday this week')->format('Y-m-d'), $anchor->modify('sunday this week')->format('Y-m-d')],
        'monthly' => [$anchor->format('Y-m-01'), $anchor->format('Y-m-t')],
        default => [$from, $to],
    };
    if (!attendance_date($to) || $from > $to || $from < '1900-01-01' || $to > '9998-12-31') {
        throw new RuntimeException('Choose a valid start date and end date.', 422);
    }
    if (strtotime($to) > strtotime($from . ' +365 days')) {
        throw new RuntimeException('Choose a reporting period of up to 366 calendar days.', 422);
    }
    if ($location !== '' && !isset($locations[$location])) throw new RuntimeException('You do not have access to that parking location.', 403);
    if (($employee !== '' && !isset($staff[$employee])) || ($type === 'individual' && $employee === '')) {
        throw new RuntimeException('Choose an employee available in your attendance scope.', 422);
    }
    if ($status !== '' && !in_array($status, ATTENDANCE_REPORT_STATUSES, true)) throw new RuntimeException('Choose a valid attendance status.', 422);
    if ($exception !== '' && !isset(ATTENDANCE_EXCEPTION_LABELS[$exception])) throw new RuntimeException('Choose a valid exception type.', 422);
    return array_replace($filters, ['from' => $from, 'to' => $to]);
}

function attendance_report_matches(array $row, string $status): bool {
    return match ($status) {
        '' => true,
        'pending' => $row['open'],
        'rejected' => $row['review_status'] === 'rejected',
        'checked_out' => $row['out'] !== null,
        'missing_checkout' => $row['checkout_missing'],
        default => $row['state'] === $status,
    };
}

function attendance_report(PDO $db, array $actor, array $locations, array $staff, array $query, int $now, string $type = 'custom', bool $allLocations = false): array {
    $filters = attendance_report_filters($query, $locations, $staff, $now, $type);
    ['from' => $from, 'to' => $to, 'employee' => $employee, 'location' => $location, 'status' => $status,
        'search' => $search, 'exception' => $exception] = $filters;

    $where = 's.shift_date BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($location !== '') {
        $where .= ' AND s.lot_id = ?';
        $params[] = (int)$location;
    }
    if ($employee !== '') {
        $where .= ' AND s.staff_id = ?';
        $params[] = (int)$employee;
    }
    $policy = attendance_report_policy($db);
    $holidays = attendance_report_holidays($db, $from, $to);
    $periodClosed = $now >= strtotime($to . ' +1 day');
    $records = attendance_rows($db, $actor, $where, $params, false, ATTENDANCE_REPORT_MAX_ROWS + 1, $allLocations);
    if (count($records) > ATTENDANCE_REPORT_MAX_ROWS) {
        throw new RuntimeException('This report has too many shifts. Narrow the dates, employee, or location.', 422);
    }
    $facts = [];
    foreach ($records as $row) {
        $id = $row['staff_id'];
        $name = $staff[$id]['name'] ?? '';
        if ($search !== '' && mb_stripos($name . ' ' . attendance_staff_id($id), $search, 0, 'UTF-8') === false) continue;
        $facts[] = attendance_report_fact($row, $holidays, $policy, $now);
    }

    // Assess eligibility across the whole scoped period, even when the visible
    // status filter hides an absence or a pending review.
    $eligibility = [];
    foreach (attendance_employee_days($facts) as $id => $days) {
        $eligibility[$id] = attendance_employee_performance($days, $policy, $periodClosed)['full'];
    }
    $rows = array_values(array_filter($facts, static function (array $row) use ($status, $type, $exception, $eligibility): bool {
        if (!attendance_report_matches($row, $status)) return false;
        return match ($type) {
            'full' => $eligibility[$row['staff_id']] === 'full',
            'exceptions' => $row['exceptions'] !== [] && ($exception === '' || in_array($exception, $row['exceptions'], true)),
            default => true,
        };
    }));
    $metrics = attendance_report_metrics($rows, $policy, $periodClosed, $eligibility);
    return ['type' => $type, 'filters' => $filters, 'rows' => $rows, 'policy' => $policy, 'holidays' => $holidays,
        'period_closed' => $periodClosed, 'generated_at' => $now] + $metrics;
}

function attendance_report_month(mixed $value, int $now): string {
    if ($value === null || $value === '') return date('Y-m', $now);
    if (!is_string($value) || !attendance_date($value . '-01') || $value < '1900-01' || $value > '9998-12') {
        throw new RuntimeException('Choose a valid calendar month.', 422);
    }
    return $value;
}

function attendance_monthly_employee(array $staff, string $query): string {
    if ($query === '') return '';
    $query = mb_strtolower($query, 'UTF-8');
    $matches = $exactNames = [];
    foreach ($staff as $id => $employee) {
        $staffId = attendance_staff_id($id);
        $name = mb_strtolower($employee['name'], 'UTF-8');
        $label = $name . ' · ' . strtolower($staffId);
        if ($label === $query || strcasecmp($staffId, $query) === 0) return (string)$id;
        if ($name === $query) $exactNames[] = $id;
        if (str_contains($label, $query)) $matches[] = $id;
    }
    $results = $exactNames ?: $matches;
    if (count($results) === 1) return (string)$results[0];
    throw new RuntimeException($results ? 'More than one employee matches. Choose a suggestion or enter the full staff ID.'
        : 'No employees match your search.', 422);
}

function attendance_calendar(PDO $db, array $actor, array $locations, array $staff, string $employee, string $month, int $now, string $location = '', string $workday = ''): array {
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));
    $report = attendance_report($db, $actor, $locations, $staff,
        ['from' => $first, 'to' => $last, 'employee' => $employee, 'location' => $location], $now, allLocations: true);
    $byDate = [];
    foreach (array_reverse($report['rows']) as $row) $byDate[$row['shift_date']][] = $row;
    $days = [];
    for ($date = new DateTimeImmutable($first); $date->format('Y-m') === $month; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $shifts = $byDate[$key] ?? [];
        if ($key === $workday) {
            [$access, $params] = attendance_visibility($actor);
            foreach ($shifts as &$shift) {
                $shift['calendar_photo'] = (bool)attendance_query($db, "SELECT 1 FROM attendance_photos p
                    JOIN attendance_shifts s ON s.id = p.shift_id WHERE $access AND s.id = ?",
                    [...$params, $shift['id']])->fetchColumn();
            }
            unset($shift);
        }
        $holiday = $report['holidays'][$key] ?? null;
        $state = attendance_report_day($shifts)['state'];
        if (!$shifts) {
            $leave = $actor['role'] !== 'admin' && attendance_leave([
                'staff_id' => (int)$employee, 'starts_at' => $date->getTimestamp(), 'ends_at' => $date->modify('+1 day')->getTimestamp(),
            ]);
            $state = match (true) {
                $leave => 'leave',
                $holiday !== null => 'holiday',
                default => 'rest',
            };
        }
        $days[$key] = ['shifts' => $shifts, 'state' => $state, 'holiday' => $holiday];
    }
    return [
        'month' => $month, 'days' => $days,
        'performance' => $report['people'][(int)$employee]
            ?? attendance_employee_performance([], $report['policy'], $report['period_closed']),
    ];
}

function attendance_report_page(array $rows, mixed $page): array {
    $size = 25;
    $pages = max(1, (int)ceil(count($rows) / $size));
    $current = min($pages, filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1);
    return ['current' => $current, 'pages' => $pages, 'rows' => array_slice($rows, ($current - 1) * $size, $size)];
}

function attendance_audit_values(string $json): string {
    $labels = [
        'status' => 'Attendance status', 'request_id' => 'Review ID', 'check_in' => 'Check In', 'check_out' => 'Check Out',
        'lot_id' => 'Location ID', 'starts_at' => 'Start time', 'ends_at' => 'End time', 'id' => 'Shift ID',
        'radius_m' => 'Allowed distance (m)', 'accuracy_m' => 'Maximum GPS error (m)', 'grace_minutes' => 'Late grace period (minutes)',
        'admin_id' => 'Admin ID', 'granted' => 'Access granted',
        'require_checkout' => 'Check-out required', 'excuse_holidays' => 'Excuse public holidays',
        'holiday_date' => 'Holiday date', 'holiday_name' => 'Holiday name',
    ];
    $parts = [];
    foreach (json_decode($json, true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
        if (!isset($labels[$key])) continue;
        $formatted = match ($key) {
            'check_in', 'check_out', 'starts_at', 'ends_at' => e(attendance_clock($value)),
            'granted', 'require_checkout', 'excuse_holidays' => te($value ? 'Yes' : 'No'),
            'status' => attendance_badge($value),
            default => e($value),
        };
        $parts[] = te($labels[$key]) . ' · ' . $formatted;
    }
    return $parts ? implode('<br>', $parts) : '—';
}
