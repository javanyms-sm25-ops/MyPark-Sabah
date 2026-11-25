<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/localization.php';
require_once dirname(__DIR__) . '/includes/attendance-actions.php';
require_once dirname(__DIR__) . '/includes/attendance-report.php';
require_once dirname(__DIR__) . '/includes/attendance-report-view.php';
require_once dirname(__DIR__) . '/includes/attendance-export.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function rejects(callable $operation, int $status): void {
    try {
        $operation();
    } catch (RuntimeException $failure) {
        expect($failure->getCode() === $status, 'Unexpected error status.');
        return;
    }
    throw new RuntimeException('Expected an error.');
}

$cases = 0;
function test(string $name, callable $operation): void {
    $operation();
    $GLOBALS['cases']++;
    echo 'PASS ' . $name . PHP_EOL;
}

function sample_shift(PDO $db, array $super, int $staffId, int $lot, string $day, array $capture = [], string $start = '08:00', string $end = '16:00'): int {
    attendance_save_shift($db, $super, ['staff_id' => $staffId, 'lot_id' => $lot, 'shift_date' => $day, 'start_time' => $start, 'end_time' => $end]);
    $id = attendance_rows($db, $super, 's.shift_date = ? AND s.staff_id = ?', [$day, $staffId])[0]['id'];
    if ($capture) {
        [$in, $out, $zone] = $capture;
        attendance_init_record($db, $id, strtotime($day . ' ' . $start), ATTENDANCE_SETTINGS);
        attendance_query($db, "UPDATE attendance_records SET checked_in = ?, checked_out = ?, in_zone = ?,
            out_zone = ?, in_capture = 'captured', in_lat = ?, in_lng = ? WHERE shift_id = ?",
            [strtotime($in), $out === null ? null : strtotime($out), $zone, $out === null ? null : 'within', 5.12345678, 116.87654321, $id]);
    }
    return $id;
}

$accounts = [
    1 => ['id' => 1, 'role' => 'superadmin', 'name' => 'Fictional Super Admin', 'blocked' => false],
    2 => ['id' => 2, 'role' => 'admin', 'name' => 'Fictional Admin', 'blocked' => false],
    3 => ['id' => 3, 'role' => 'staff', 'name' => 'Fictional Nur', 'blocked' => false],
    4 => ['id' => 4, 'role' => 'user', 'name' => 'Fictional Customer', 'blocked' => false],
    5 => ['id' => 5, 'role' => 'staff', 'name' => '=2+2', 'blocked' => false],
    6 => ['id' => 6, 'role' => 'staff', 'name' => 'Fictional 李明', 'blocked' => false],
    7 => ['id' => 7, 'role' => 'staff', 'name' => 'Fictional Ravi', 'blocked' => false],
    8 => ['id' => 8, 'role' => 'staff', 'name' => 'Fictional Mei', 'blocked' => false],
];
$_SESSION = [
    'demo_users' => $accounts,
    'demo_lots' => [
        ['id' => 1, 'name' => 'Waterfront demo', 'lat' => 5.98, 'lng' => 116.07],
        ['id' => 2, 'name' => 'East office demo', 'lat' => 5.84, 'lng' => 118.11],
    ],
    'vehicle_account_scope' => 'report-test-workspace',
];
$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys = ON');
$includes = dirname(__DIR__) . '/includes/';
$db->exec(file_get_contents($includes . 'attendance-schema.sql'));
[$super, $admin] = [$accounts[1], $accounts[2]];
$staff = array_filter($accounts, static fn($account) => $account['role'] === 'staff');
$locations = attendance_locations($db, $super);
$now = strtotime('2026-11-20 12:00:00');
$period = ['from' => '2026-11-01', 'to' => '2026-11-18'];

attendance_query($db, 'INSERT INTO attendance_shifts (scope, staff_id, lot_id, lot_name, lot_lat, lot_lng, shift_date, starts_at, ends_at)
    VALUES (?, 3, 1, ?, 5.98, 116.07, ?, ?, ?)', [vehicle_account_scope(), 'Waterfront demo', '2026-11-01', strtotime('2026-11-01 08:00'), strtotime('2026-11-01 16:00')]);
$db->exec(file_get_contents($includes . 'attendance-analytics-schema.sql'));
test('Version 2 migration preserves scheduled rows and adds clarification columns', static function () use ($db, $super) {
    $row = attendance_row($db, $super, 1);
    expect($row['staff_id'] === 3 && $row['shift_date'] === '2026-11-01', 'Legacy shift lost.');
    expect(attendance_report_policy($db) === ['require_checkout' => 1, 'excuse_holidays' => 1], 'Unexpected default policy.');
    // The rest of the case data starts on the second day.
    $db->exec('DELETE FROM attendance_shifts WHERE id = 1');
});

attendance_set_scope($db, $super, ['action' => 'grant_scope', 'admin_id' => '2', 'lot_id' => '1']);
attendance_save_holiday($db, $super, ['holiday_date' => '2026-11-04', 'holiday_name' => 'Fictional public holiday']);
staff_leave_submit($accounts[3], ['start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'reason' => 'Private fictional leave reason']);
staff_leave_review($admin, ['id' => 1, 'decision' => 'approved']);

$onTime = sample_shift($db, $super, 3, 1, '2026-11-02', ['2026-11-02 08:00', '2026-11-02 16:00', 'within']);
sample_shift($db, $super, 3, 1, '2026-11-03', ['2026-11-03 09:00', '2026-11-03 16:00', 'within']);
sample_shift($db, $super, 3, 1, '2026-11-04');
sample_shift($db, $super, 3, 1, '2026-11-05');
sample_shift($db, $super, 3, 1, '2026-11-06', ['2026-11-06 22:00', '2026-11-07 06:00', 'within'], '22:00', '06:00');
sample_shift($db, $super, 3, 1, '2026-11-20', ['2026-11-20 08:00', null, 'within']);
sample_shift($db, $super, 5, 1, '2026-11-02');
sample_shift($db, $super, 5, 1, '2026-11-03', ['2026-11-03 08:01', '2026-11-03 16:00', 'within']);
$outside = sample_shift($db, $super, 5, 1, '2026-11-07', ['2026-11-07 08:02', null, 'outside']);
attendance_request($db, $outside, 'evidence', 'Private location reason', strtotime('2026-11-07 08:02'));
$other = 0;
for ($i = 1; $i <= 18; $i++) {
    $day = sprintf('2026-11-%02d', $i);
    $other = sample_shift($db, $super, 6, 2, $day, [$day . ' 08:00', $day . ' 16:00', 'within']);
}
sample_shift($db, $super, 7, 1, '2026-11-02', ['2026-11-02 08:01', null, 'within']);
$correction = sample_shift($db, $super, 8, 1, '2026-11-02');
attendance_correct($db, $accounts[8], $correction, ['reason' => 'Private proposed correction reason',
    'proposed_in' => '2026-11-02T08:05', 'proposed_out' => '2026-11-02T16:00'], $now);

$adminLots = attendance_locations($db, $admin);
$adminStaff = attendance_report_staff($db, $admin, $staff);
$superReport = fn(array $query = [], string $type = 'custom') => attendance_report($db, $super, $locations, $staff, $query + $period, $now, $type);
$adminReport = fn(array $query = [], string $type = 'custom') => attendance_report($db, $admin, $adminLots, $adminStaff, $query + $period, $now, $type);

test('Full attendance is separate from punctuality and counts paired overnight hours', static function () use ($superReport) {
    $r = $superReport(['employee' => '3']);
    $p = $r['people'][3];
    expect($p['scheduled_days'] === 3 && $p['present_days'] === 3 && $p['on_time_days'] === 2 && $p['late_days'] === 1, 'Incorrect days.');
    expect($p['hours'] === 23.0 && $p['attendance_rate'] === 100.0 && $p['punctuality_rate'] === 66.7, 'Incorrect hours or rates.');
    expect($p['leave_days'] === 1 && $p['full'] === 'full', 'Excused shift prevented full attendance.');
});

test('Active shifts and unresolved reviews do not become absent or earn full attendance', static function () use ($superReport) {
    $current = $superReport(['from' => '2026-11-20', 'to' => '2026-11-20', 'employee' => '3'])['people'][3];
    expect($current['full'] === 'open' && $current['absent_days'] === 0 && $current['attendance_rate'] === null, 'Unfinished shift was settled.');
    $review = $superReport(['employee' => '8'])['people'][8];
    expect($review['full'] === 'review' && $review['absent_days'] === 0 && $review['attendance_rate'] === null, 'Pending correction was penalized.');
    $empty = $superReport(['from' => '2026-10-15', 'to' => '2026-10-16']);
    expect($empty['summary']['attendance_rate'] === null && $empty['summary']['full'] === 0, 'Empty rate or full count is wrong.');
});

test('Status filtering counts visible shifts without hiding absences from full attendance', static function () use ($adminReport) {
    $filtered = $adminReport(['employee' => '5', 'status' => 'on_time']);
    expect(count($filtered['rows']) === 1 && $filtered['summary']['on_time'] === 1, 'Status filter did not apply.');
    expect($filtered['summary']['full'] === 0 && $filtered['people'][5]['full'] === 'review', 'Status filter granted false full attendance.');
    expect($adminReport(['status' => 'missing'])['summary']['missing'] === 1, 'Missing check-in count incorrect.');
});

test('Location, employee, search, date normalization and report types share the same records', static function () use ($superReport, $adminReport) {
    expect($superReport(['search' => 'stf-0006', 'location' => '2'])['summary']['scheduled'] === 1, 'Search or location selection is incorrect.');
    expect($adminReport(['from' => '2026-11-03'], 'daily')['filters']['to'] === '2026-11-03', 'Daily normalization failed.');
    $week = $adminReport(['from' => '2026-11-08'], 'weekly');
    expect($week['filters']['from'] === '2026-11-02' && $week['filters']['to'] === '2026-11-08', 'Weekly normalization failed.');
    expect($adminReport(['from' => '2026-11-03'], 'monthly')['filters']['to'] === '2026-11-30', 'Monthly normalization failed.');
    expect(count($adminReport([], 'full')['people']) === 1, 'Full attendance report does not represent the policy.');
    expect(count($adminReport(['employee' => '3'], 'individual')['people']) === 1, 'Individual report did not isolate an employee.');
    expect(count($superReport([], 'location')['locations']) === 2, 'Location summary lost a location.');
    $leaveOnly = $adminReport(['employee' => '3', 'from' => '2026-11-05'], 'daily');
    expect($leaveOnly['summary']['scheduled'] === 1 && $leaveOnly['summary']['leave'] === 1
        && $leaveOnly['people'][3]['scheduled_days'] === 0, 'Approved leave should stay in the scheduled employee headcount.');
    rejects(fn() => $adminReport([], 'individual'), 422);
    rejects(fn() => $adminReport(['from' => ['bad']]), 422);
});

test('Admins, staff and workspaces are scoped on the server', static function () use ($db, $super, $admin, $accounts, $other, $adminReport, $superReport) {
    expect($superReport()['summary']['scheduled'] === 5 && $adminReport()['summary']['scheduled'] === 4, 'Scope counts wrong.');
    rejects(fn() => $adminReport(['location' => '2']), 403);
    rejects(fn() => $adminReport(['employee' => '6']), 422);
    rejects(fn() => attendance_row($db, $admin, $other), 404);
    rejects(fn() => attendance_row($db, $accounts[3], $other), 404);
    rejects(fn() => attendance_row($db, $accounts[4], $other), 403);
    $_SESSION['vehicle_account_scope'] = 'unrelated-test-workspace';
    try { rejects(fn() => attendance_row($db, $super, $other), 404); }
    finally { $_SESSION['vehicle_account_scope'] = 'report-test-workspace'; }
});

test('Calendar groups overnight shifts on the start date and distinguishes exempt days', static function () use ($db, $accounts, $now) {
    $cal = attendance_calendar($db, $accounts[3], [], [3 => $accounts[3]], '3', '2026-11', $now);
    expect($cal['days']['2026-11-04']['state'] === 'holiday' && $cal['days']['2026-11-05']['state'] === 'leave', 'Excused days incorrect.');
    expect($cal['days']['2026-11-07']['state'] === 'rest', 'Overnight shift was counted twice.');
    expect(date('Y-m-d', $cal['days']['2026-11-06']['shifts'][0]['out']) === '2026-11-07', 'Overnight checkout date was lost.');
    expect($cal['days']['2026-11-03']['state'] === 'late', 'Late day lost its status.');
    rejects(fn() => attendance_report_month('2026-02-31', $now), 422);
});

test('Policy can excuse holidays and require completed check-outs', static function () use ($db, $super, $admin, $superReport) {
    expect($superReport(['employee' => '7'])['people'][7]['full'] === 'not_full', 'Missing checkout unexpectedly qualified.');
    rejects(fn() => attendance_save_report_policy($db, $admin, []), 403);
    rejects(fn() => attendance_save_holiday($db, $super, ['holiday_date' => '2026-11-04', 'holiday_name' => ['bad']]), 422);
    attendance_save_report_policy($db, $super, ['require_checkout' => '0', 'excuse_holidays' => '0']);
    expect($superReport(['employee' => '7'])['people'][7]['full'] === 'full', 'Optional checkout was not respected.');
    expect($superReport(['employee' => '3'])['people'][3]['absent_days'] === 1, 'Required holiday shift was hidden.');
    attendance_save_report_policy($db, $super, ['require_checkout' => '1', 'excuse_holidays' => '1']);
});

test('Late, missing check-out, GPS and correction exceptions remain separate and sortable', static function () use ($outside, $adminReport) {
    $outsideRows = $adminReport(['exception' => 'outside'], 'exceptions')['rows'];
    expect(array_column($outsideRows, 'id') === [$outside] && $outsideRows[0]['open'], 'Outside-zone review did not filter.');
    expect(count($adminReport(['exception' => 'missing_checkout'], 'exceptions')['rows']) === 2, 'Missing check-outs are not correct.');
    expect(count($adminReport(['exception' => 'correction'], 'exceptions')['rows']) === 1, 'Correction queue is incorrect.');
});

test('Clarification can only be answered by the employee and decisions are audited', static function () use ($db, $admin, $accounts, $correction, $now, $adminReport) {
    $id = attendance_query($db, 'SELECT id FROM attendance_requests WHERE shift_id = ?', [$correction])->fetchColumn();
    $fields = ['request_id' => $id, 'reason' => 'Private question', 'decision' => 'clarification'];
    attendance_decide($db, $admin, $correction, $fields);
    expect($adminReport(['employee' => '8'])['rows'][0]['state'] === 'clarification', 'Clarification missing from report.');
    rejects(fn() => attendance_decide($db, $admin, $correction, $fields), 409);
    rejects(fn() => attendance_reply($db, $accounts[3], $correction, $fields, $now), 404);
    attendance_reply($db, $accounts[8], $correction, ['request_id' => $id, 'reason' => 'Private reply'], $now);
    rejects(fn() => attendance_reply($db, $accounts[8], $correction, $fields, $now), 409);
    attendance_decide($db, $admin, $correction, array_replace($fields, ['decision' => 'approved', 'reason' => 'Private reviewer reason']));
    $row = attendance_row($db, $admin, $correction);
    expect($row['checked_in'] === null && $row['corrected_in'] === strtotime('2026-11-02 08:05'), 'Original evidence was overwritten.');
    expect($adminReport(['employee' => '8'])['people'][8]['full'] === 'full', 'Approved correction was ignored.');
    $audit = attendance_query($db, "SELECT * FROM attendance_audit WHERE shift_id = ? AND action = 'Attendance review' ORDER BY id", [$correction])->fetchAll();
    expect(count($audit) === 2 && $audit[0]['admin_id'] === 2 && str_contains($audit[0]['after_value'], 'clarification')
        && str_contains($audit[1]['after_value'], 'approved'), 'Review decisions were not audited.');
});

$data = attendance_export_data($superReport(), $staff, $locations);
$csv = attendance_report_csv($data);
$xlsx = attendance_report_xlsx($data);
$pdf = attendance_report_pdf($data);

test('CSV matches displayed shifts, is UTF-8, and escapes formulas and private evidence', static function () use ($csv, $data) {
    expect(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, '李明'), 'CSV encoding is wrong.');
    expect(str_contains($csv, "'=2+2") && str_contains($csv, '2026-11-07 06:00:00'), 'CSV formula or overnight time is wrong.');
    expect(count(explode("\r\n", trim($csv))) === count($data['detail']) + 1, 'CSV row count differs from preview.');
    expect(!str_contains($csv, '5.12345678') && !str_contains($csv, 'Private reviewer reason'), 'CSV contains private evidence.');
});

test('PDF is a binary report with more than one page', static function () use ($pdf) {
    expect(str_starts_with($pdf, '%PDF-') && preg_match_all('~/Type\s*/Page\b~', $pdf) >= 2, 'PDF is invalid or lacks pagination.');
});

test('Excel is a real workbook with typed values, filters, freeze panes and conditional formatting', static function () use ($xlsx, $data) {
    expect(class_exists('ZipArchive'), 'Run the test with -d extension=zip to inspect Excel XML.');
    $path = tempnam(sys_get_temp_dir(), 'mypark-excel-');
    file_put_contents($path, $xlsx);
    $zip = new ZipArchive();
    try {
        expect($zip->open($path) === true, 'XLSX is not a ZIP workbook.');
        $sheets = $zip->getFromName('xl/workbook.xml');
        $detail = $zip->getFromName('xl/worksheets/sheet2.xml');
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        expect(str_contains($sheets, 'Summary') && str_contains($sheets, 'Detailed Attendance'), 'Wrong worksheets.');
        expect(str_contains($detail, 'pane ySplit="1"') && str_contains($detail, '<autoFilter') && str_contains($detail, '<conditionalFormatting'), 'Workbook navigation or formatting missing.');
        expect(str_contains($styles, 'yyyy-mm-dd hh:mm:ss') && str_contains($styles, '<dxfs'), 'Typed dates or conditional styles missing.');
        expect(substr_count($detail, '<row ') === count($data['detail']) + 1, 'Excel row count differs from preview.');
        expect(str_contains($strings, '=2+2') && str_contains($strings, '李明') && !str_contains($detail, '<f>'), 'Excel text parsed incorrectly.');
        expect(!str_contains($strings, '5.12345678') && !str_contains($strings, 'Private reviewer reason'), 'Excel contains private evidence.');
        foreach ([$sheets, $detail, $styles, $strings] as $xml) expect(simplexml_load_string($xml) !== false, 'Invalid workbook XML.');
    } finally {
        if ($zip->status === ZipArchive::ER_OK) $zip->close();
        unlink($path);
    }
});

if (isset($argv[1]) && is_dir($argv[1])) {
    file_put_contents($argv[1] . DIRECTORY_SEPARATOR . 'attendance-test.pdf', $pdf);
    file_put_contents($argv[1] . DIRECTORY_SEPARATOR . 'attendance-test.xlsx', $xlsx);
    file_put_contents($argv[1] . DIRECTORY_SEPARATOR . 'attendance-test.csv', $csv);
}
echo "Verified $cases test groups." . PHP_EOL;
