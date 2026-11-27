<?php

const ATTENDANCE_EXPORT_COLUMNS = [
    'shift_id' => 'Shift ID', 'shift_date' => 'Shift Date (MYT)', 'staff_id' => 'Staff ID', 'name' => 'Employee',
    'location' => 'Assigned Location', 'starts_at' => 'Scheduled Start (MYT)', 'ends_at' => 'Scheduled End (MYT)',
    'actual_in' => 'Actual Check-In (MYT)', 'actual_out' => 'Actual Check-Out (MYT)',
    'effective_in' => 'Effective Check-In (MYT)', 'effective_out' => 'Effective Check-Out (MYT)',
    'hours' => 'Recorded Working Hours', 'status' => 'Attendance Status', 'in_zone' => 'Check-In GPS Status',
    'out_zone' => 'Check-Out GPS Status', 'review' => 'Review Outcome', 'exceptions' => 'Exceptions', 'holiday' => 'Public Holiday',
];

function attendance_export_datetime(?int $value): string {
    return $value === null ? '' : date('Y-m-d H:i:s', $value);
}

function attendance_export_label(?string $status): string {
    return $status !== null && isset(ATTENDANCE_LABELS[$status]) ? t(ATTENDANCE_LABELS[$status]) : '';
}

function attendance_export_data(array $report, array $staff, array $locations): array {
    $filters = $report['filters'];
    $selectedEmployee = $staff[$filters['employee']] ?? null;
    $scope = [
        t('Reporting period') => $filters['from'] . ' – ' . $filters['to'] . ' MYT',
        t('Employee') => $selectedEmployee ? $selectedEmployee['name'] . ' · ' . attendance_staff_id($selectedEmployee['id']) : t('All permitted employees'),
        t('Parking location') => $locations[$filters['location']]['name'] ?? t('All permitted locations'),
        t('Attendance status') => $filters['status'] !== '' ? attendance_export_label($filters['status']) : t('All statuses'),
        t('Generated') => attendance_export_datetime($report['generated_at']) . ' MYT',
    ];
    if ($filters['search'] !== '') $scope[t('Employee search')] = $filters['search'];
    if ($report['type'] === 'exceptions' && $filters['exception'] !== '') {
        $scope[t('Exception type')] = t(ATTENDANCE_EXCEPTION_LABELS[$filters['exception']]);
    }
    $metrics = [];
    foreach (ATTENDANCE_REPORT_METRICS as $key => $label) $metrics[t($label)] = $report['summary'][$key];

    $detail = [];
    $places = [];
    foreach ($report['rows'] as $row) {
        $id = $row['staff_id'];
        $places[$id][$row['lot_id']] = $row['lot_name'];
        $detail[] = [
            'shift_id' => $row['id'],
            'shift_date' => $row['shift_date'],
            'staff_id' => attendance_staff_id($id),
            'name' => $staff[$id]['name'] ?? attendance_staff_id($id),
            'location' => $row['lot_name'],
            'starts_at' => attendance_export_datetime($row['starts_at']),
            'ends_at' => attendance_export_datetime($row['ends_at']),
            'actual_in' => attendance_export_datetime($row['checked_in']),
            'actual_out' => attendance_export_datetime($row['checked_out']),
            'effective_in' => attendance_export_datetime($row['in']),
            'effective_out' => attendance_export_datetime($row['out']),
            'hours' => $row['in'] !== null && $row['out'] !== null ? round($row['seconds'] / 3600, 2) : null,
            'status' => attendance_export_label($row['state']),
            'in_zone' => attendance_export_label($row['in_zone']),
            'out_zone' => attendance_export_label($row['out_zone']),
            'review' => attendance_review_label($row),
            'exceptions' => implode(' · ', array_map(static fn($key) => t(ATTENDANCE_EXCEPTION_LABELS[$key]), $row['exceptions'])),
            'holiday' => $row['holiday_name'] ?? '',
        ];
    }
    $people = [];
    foreach ($report['people'] as $id => $person) {
        $people[] = array_replace([
            'staff_id' => attendance_staff_id($id),
            'name' => $staff[$id]['name'] ?? attendance_staff_id($id),
            'location' => implode(', ', $places[$id] ?? []),
        ], $person, ['full' => t(ATTENDANCE_FULL_LABELS[$person['full']])]);
    }

    return ['title' => t(ATTENDANCE_REPORT_TYPES[$report['type']]), 'scope' => $scope, 'metrics' => $metrics,
        'filters' => $filters, 'detail' => $detail, 'people' => $people, 'type' => $report['type'],
        'locations' => array_values($report['locations'])];
}

function attendance_csv_cell(mixed $value): string {
    $text = (string)$value;
    // Excel may ignore invisible whitespace or interpret full-width formula prefixes.
    return preg_match('/\A[\p{Z}\p{Cc}\p{Cf}]*[=+@\-\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $text)
        || preg_match('/\A[\t\r\n]/', $text) ? "'" . $text : $text;
}

function attendance_report_csv(array $data): string {
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "\xEF\xBB\xBF");
    $writeRow = static function (array $row) use ($stream): void {
        if (fputcsv($stream, $row, ',', '"', '') === false) throw new RuntimeException('Could not generate CSV.');
        // PHP 8.0 writes LF and does not accept a line-ending argument.
        fseek($stream, -1, SEEK_CUR);
        fwrite($stream, "\r\n");
    };
    $writeRow(array_map('t', ATTENDANCE_EXPORT_COLUMNS));
    foreach ($data['detail'] as $row) {
        if ($row['hours'] !== null) $row['hours'] = number_format($row['hours'], 2, '.', '');
        $writeRow(array_map('attendance_csv_cell', $row));
    }
    rewind($stream);
    $bytes = stream_get_contents($stream);
    fclose($stream);
    if ($bytes === false) throw new RuntimeException('Could not generate CSV.');
    return $bytes;
}

function attendance_send_report(array $report, array $staff, array $locations, string $format): void {
    $data = attendance_export_data($report, $staff, $locations);
    [$type, $content] = match ($format) {
        'csv' => ['text/csv; charset=utf-8', attendance_report_csv($data)],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', attendance_report_xlsx($data)],
        'pdf' => ['application/pdf', attendance_report_pdf($data)],
        default => throw new RuntimeException('Choose a valid report format.', 422),
    };
    $filename = 'mypark-attendance-' . $data['type'] . '-' . $data['filters']['from'] . '.' . $format;
    header('Cache-Control: private, no-store');
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('X-Content-Type-Options: nosniff');
    echo $content;
}

function attendance_report_pdf(array $data): string {
    require_once __DIR__ . '/attendance-export-pdf.php';
    return attendance_build_pdf($data);
}

function attendance_report_xlsx(array $data): string {
    require_once __DIR__ . '/attendance-export-xlsx.php';
    return attendance_build_xlsx($data);
}
