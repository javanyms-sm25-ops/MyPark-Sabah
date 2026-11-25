<?php

const ATTENDANCE_REPORT_STATUSES = ['scheduled', 'leave', 'on_time', 'late', 'checked_out', 'missing', 'pending', 'rejected'];

function attendance_report(PDO $db, array $actor, array $locations, array $staff, array $query, int $now): array {
    $today = date('Y-m-d', $now);
    $filters = [];
    foreach (['from', 'to', 'employee', 'location', 'status', 'search'] as $field) {
        $value = $query[$field] ?? '';
        if (!is_string($value) || strlen($value) > 320) throw new RuntimeException('Invalid attendance filter.', 422);
        $filters[$field] = trim($value);
    }
    ['from' => $from, 'to' => $to, 'employee' => $employee, 'location' => $location, 'status' => $status, 'search' => $search] = $filters;
    if ($from === '' && $to === '') $from = $to = $today;
    if (!attendance_date($from) || !attendance_date($to) || $from > $to) {
        throw new RuntimeException('Choose a valid start date and end date.', 422);
    }
    if ($location !== '' && !isset($locations[$location])) throw new RuntimeException('You do not have access to that parking location.', 403);
    if ($employee !== '' && !isset($staff[$employee])) throw new RuntimeException('Choose a valid employee.', 422);
    if ($status !== '' && !in_array($status, ATTENDANCE_REPORT_STATUSES, true)) throw new RuntimeException('Choose a valid attendance status.', 422);
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

    $scheduled = [];
    $checkedIn = [];
    $counts = ['On-Time Check-Ins' => 0, 'Late Check-Ins' => 0, 'Missing Check-Ins' => 0, 'Outside-Zone Check-Ins' => 0, 'Pending Attendance Reviews' => 0];
    $visible = [];
    foreach (attendance_rows($db, $actor, $where, $params) as $record) {
        $id = $record['staff_id'];
        $name = $staff[$id]['name'] ?? '';
        if ($search !== '' && mb_stripos($name . ' ' . attendance_staff_id($id), $search, 0, 'UTF-8') === false) continue;
        $actualStatus = attendance_status($record, $now);
        $punctuality = attendance_punctuality($record);
        if ($status !== '' && $status !== $actualStatus && $status !== $punctuality) continue;
        $visible[] = $record;
        if (!attendance_leave($record)) $scheduled[$id] = true;
        if (attendance_times($record)[0] !== null) $checkedIn[$id] = true;
        if ($punctuality === 'on_time') $counts['On-Time Check-Ins']++;
        if ($punctuality === 'late') $counts['Late Check-Ins']++;
        if ($actualStatus === 'missing') $counts['Missing Check-Ins']++;
        if ($record['in_zone'] === 'outside') $counts['Outside-Zone Check-Ins']++;
        if ($actualStatus === 'pending') $counts['Pending Attendance Reviews']++;
    }
    return ['filters' => $filters, 'rows' => $visible, 'summary' => [
        'Total Scheduled Employees' => count($scheduled), 'Employees Checked In' => count($checkedIn),
    ] + $counts];
}

function attendance_export(array $rows, array $staff, int $now): void {
    require_once __DIR__ . '/dashboard-data.php';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mypark-attendance.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Staff ID', 'Employee', 'Assigned Location', 'Shift Date (MYT)', 'Actual Check In (MYT)', 'Actual Check Out (MYT)',
        'Effective Check In (MYT)', 'Effective Check Out (MYT)', 'Duration', 'Attendance Status', 'Punctuality',
        'Check-In Location Status', 'Check-In Distance (m)', 'Check-In Accuracy (m)', 'Check-Out Location Status',
        'Check-Out Distance (m)', 'Check-Out Accuracy (m)'], ',', '"', '');
    foreach ($rows as $row) {
        [$in, $outTime] = attendance_times($row);
        fputcsv($out, array_map('ops_csv_cell', [
            attendance_staff_id($row['staff_id']), $staff[$row['staff_id']]['name'] ?? '', $row['lot_name'], $row['shift_date'],
            attendance_clock($row['checked_in']), attendance_clock($row['checked_out']), attendance_clock($in), attendance_clock($outTime),
            attendance_duration($row), ATTENDANCE_LABELS[attendance_status($row, $now)], ATTENDANCE_LABELS[attendance_punctuality($row)] ?? '',
            ATTENDANCE_LABELS[$row['in_zone']] ?? '', $row['in_distance'], $row['in_accuracy'], ATTENDANCE_LABELS[$row['out_zone']] ?? '',
            $row['out_distance'], $row['out_accuracy'],
        ]), ',', '"', '');
    }
    fclose($out);
}

function attendance_audit_values(string $json): string {
    $labels = [
        'status' => 'Attendance status', 'request_id' => 'Review ID', 'check_in' => 'Check In', 'check_out' => 'Check Out',
        'lot_id' => 'Location ID', 'starts_at' => 'Start time', 'ends_at' => 'End time', 'id' => 'Shift ID',
        'radius_m' => 'Allowed distance (m)', 'accuracy_m' => 'Maximum GPS error (m)', 'grace_minutes' => 'Late grace period (minutes)',
        'admin_id' => 'Admin ID', 'granted' => 'Access granted',
    ];
    $parts = [];
    foreach (json_decode($json, true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
        if (!isset($labels[$key])) continue;
        $formatted = match ($key) {
            'check_in', 'check_out', 'starts_at', 'ends_at' => e(attendance_clock($value)),
            'granted' => te($value ? 'Yes' : 'No'),
            'status' => attendance_badge($value),
            default => e($value),
        };
        $parts[] = te($labels[$key]) . ' · ' . $formatted;
    }
    return $parts ? implode('<br>', $parts) : '—';
}
