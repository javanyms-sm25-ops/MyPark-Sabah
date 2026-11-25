<?php

require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/staff-leave.php';

const ATTENDANCE_SETTINGS = ['radius_m' => 150, 'accuracy_m' => 100, 'grace_minutes' => 10];
const ATTENDANCE_PHOTO_LIMIT = 2000000;
const ATTENDANCE_EVIDENCE_DAYS = 90;
const ATTENDANCE_LABELS = [
    'scheduled' => 'Scheduled', 'rest' => 'Rest Day', 'leave' => 'On Approved Leave',
    'on_time' => 'On Time', 'late' => 'Late', 'checked_out' => 'Checked Out',
    'missing' => 'Missing Check-In', 'pending' => 'Pending Review', 'rejected' => 'Review Rejected',
    'clarification' => 'Clarification requested', 'unverified' => 'Unverified Check-In',
    'holiday' => 'Public Holiday', 'missing_checkout' => 'Missing Check-Out',
    'within' => 'Within Zone', 'outside' => 'Outside Zone', 'uncertain' => 'Location Uncertain',
];
const ATTENDANCE_CAPTURE = [
    'captured' => 'GPS reading received', 'denied' => 'Location permission denied',
    'failed' => 'Location unavailable', 'skipped' => 'Location not recorded',
];

function attendance_db(): PDO {
    static $db;
    if ($db instanceof PDO) return $db;
    $normalize = static fn(string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/') . '/');
    $directory = realpath(session_save_path());
    $webRoot = realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__));
    if (!$directory || !$webRoot || str_starts_with($normalize($directory), $normalize($webRoot))
        || str_starts_with($normalize($directory), $normalize(dirname(__DIR__))) || !extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Private attendance storage is unavailable.', 503);
    }
    try {
        $connection = new PDO('sqlite:' . $directory . DIRECTORY_SEPARATOR . 'attendance.sqlite3', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA busy_timeout = 5000');
        $connection->exec('PRAGMA secure_delete = ON');
        if ((int)$connection->query('PRAGMA user_version')->fetchColumn() < 2) {
            attendance_transaction($connection, function () use ($connection) {
                $version = (int)$connection->query('PRAGMA user_version')->fetchColumn();
                foreach ([1 => 'attendance-schema.sql', 2 => 'attendance-analytics-schema.sql'] as $target => $file) {
                    if ($version >= $target) continue;
                    $sql = file_get_contents(__DIR__ . '/' . $file);
                    if ($sql === false) throw new RuntimeException('Attendance migration is unavailable.', 503);
                    $connection->exec($sql);
                    $connection->exec('PRAGMA user_version = ' . $target);
                }
            });
        }
        $cutoff = time() - ATTENDANCE_EVIDENCE_DAYS * 86400;
        attendance_query($connection, 'DELETE FROM attendance_photos WHERE captured_at < ?', [$cutoff]);
        attendance_query($connection, 'UPDATE attendance_records SET in_lat = NULL, in_lng = NULL
            WHERE checked_in < ? AND in_lat IS NOT NULL', [$cutoff]);
        attendance_query($connection, 'UPDATE attendance_records SET out_lat = NULL, out_lng = NULL
            WHERE checked_out < ? AND out_lat IS NOT NULL', [$cutoff]);
        return $db = $connection;
    } catch (PDOException $error) {
        throw new RuntimeException('Private attendance storage is unavailable.', 503, $error);
    }
}

function attendance_query(PDO $db, string $sql, array $params = []): PDOStatement {
    $stmt = $db->prepare($sql);
    foreach (array_values($params) as $index => $value) {
        $stmt->bindValue($index + 1, $value, match (true) {
            is_int($value) => PDO::PARAM_INT, $value === null => PDO::PARAM_NULL, default => PDO::PARAM_STR,
        });
    }
    $stmt->execute();
    return $stmt;
}

function attendance_transaction(PDO $db, callable $operation): mixed {
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $operation();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function attendance_accounts(): array {
    return array_column($_SESSION['demo_users'], null, 'id');
}

function attendance_locations(PDO $db, array $actor): array {
    $lots = array_column($_SESSION['demo_lots'], null, 'id');
    if ($actor['role'] === 'superadmin') return $lots;
    if ($actor['role'] !== 'admin') return [];
    $ids = attendance_query($db, 'SELECT lot_id FROM attendance_admin_locations WHERE scope = ? AND admin_id = ?',
        [vehicle_account_scope(), $actor['id']])->fetchAll(PDO::FETCH_COLUMN);
    return array_intersect_key($lots, array_flip($ids));
}

function attendance_settings(PDO $db): array {
    return attendance_query($db, 'SELECT radius_m, accuracy_m, grace_minutes FROM attendance_settings WHERE scope = ?',
        [vehicle_account_scope()])->fetch() ?: ATTENDANCE_SETTINGS;
}

function attendance_staff_id(int $id): string {
    return 'STF-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function attendance_date(mixed $value): bool {
    return is_string($value) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) === 1
        && staff_leave_date_is_valid($value);
}

function attendance_time(mixed $value): bool {
    return is_string($value) && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value) === 1;
}

function attendance_number(mixed $value, float $min, float $max): ?float {
    if (!is_scalar($value) || !is_numeric($value)) return null;
    $number = (float)$value;
    return is_finite($number) && $number >= $min && $number <= $max ? $number : null;
}

function attendance_int(mixed $value, int $min = 1, int $max = PHP_INT_MAX): int {
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($number === false) throw new RuntimeException('Enter a valid value.', 422);
    return $number;
}

function attendance_reason(mixed $value): string {
    $reason = is_string($value) ? trim($value) : '';
    if (preg_match('/\A.{1,500}\z/us', $reason) !== 1) {
        throw new RuntimeException('Enter a reason of 1 to 500 characters.', 422);
    }
    return $reason;
}

function attendance_leave(array $row): bool {
    foreach ($_SESSION['staff_leave_requests'] ?? [] as $leave) {
        if ($leave['staff_id'] !== $row['staff_id'] || $leave['status'] !== 'approved') continue;
        $from = strtotime($leave['start_date']);
        $until = strtotime($leave['end_date'] . ' +1 day');
        if ($from < $row['ends_at'] && $until > $row['starts_at']) return true;
    }
    return false;
}

function attendance_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return 12742000 * asin(sqrt(min(1.0, $a)));
}

function attendance_gps(array $fields, array $shift, array $settings): array {
    $capture = $fields['gps_status'] ?? 'skipped';
    if (!is_string($capture) || !isset(ATTENDANCE_CAPTURE[$capture])) throw new RuntimeException('Invalid GPS capture status.', 422);
    if ($capture !== 'captured') return [null, null, null, null, $capture, 'uncertain'];

    $latitude = attendance_number($fields['latitude'] ?? null, -90, 90);
    $longitude = attendance_number($fields['longitude'] ?? null, -180, 180);
    $accuracy = attendance_number($fields['accuracy'] ?? null, 0, 1000000);
    if ($latitude === null || $longitude === null || $accuracy === null) {
        throw new RuntimeException('Invalid GPS reading. Try again or request a review.', 422);
    }
    $distance = attendance_distance((float)$shift['lot_lat'], (float)$shift['lot_lng'], $latitude, $longitude);
    $zone = $accuracy > $settings['accuracy_m'] ? 'uncertain'
        : ($distance > $settings['radius_m'] ? 'outside' : 'within');
    return [$latitude, $longitude, $accuracy, round($distance, 1), $capture, $zone];
}

function attendance_photo(mixed $data): string {
    $prefix = 'data:image/jpeg;base64,';
    if (!is_string($data) || !str_starts_with($data, $prefix) || strlen($data) > (int)ceil(ATTENDANCE_PHOTO_LIMIT / 3) * 4 + strlen($prefix)) {
        throw new RuntimeException('Capture an attendance photo or request an exception.', 422);
    }
    $bytes = base64_decode(substr($data, strlen($prefix)), true);
    if (!$bytes || strlen($bytes) > ATTENDANCE_PHOTO_LIMIT || !str_starts_with($bytes, "\xff\xd8") || !str_ends_with($bytes, "\xff\xd9")) {
        throw new RuntimeException('The attendance photo is invalid or too large.', 422);
    }
    $image = @getimagesizefromstring($bytes);
    if (!$image || $image[2] !== IMAGETYPE_JPEG || $image[0] < 1 || $image[1] < 1 || max($image[0], $image[1]) > 1600
        || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/jpeg') {
        throw new RuntimeException('The attendance photo is invalid or too large.', 422);
    }
    return $bytes;
}

function attendance_visibility(array $actor): array {
    $where = 's.scope = ?';
    $params = [vehicle_account_scope()];
    if ($actor['role'] === 'staff') {
        $where .= ' AND s.staff_id = ?';
        $params[] = $actor['id'];
    } elseif ($actor['role'] === 'admin') {
        $where .= ' AND EXISTS (SELECT 1 FROM attendance_admin_locations a WHERE a.scope = s.scope AND a.admin_id = ? AND a.lot_id = s.lot_id)';
        $params[] = $actor['id'];
    } elseif ($actor['role'] !== 'superadmin') {
        throw new RuntimeException('You do not have access to attendance records.', 403);
    }
    return [$where, $params];
}

function attendance_rows(PDO $db, array $actor, string $where, array $params, bool $evidence = true, int $limit = 0): array {
    [$access, $owner] = attendance_visibility($actor);
    $rowLimit = $limit > 0 ? ' LIMIT ' . $limit : '';
    $private = $evidence ? 's.lot_lat, s.lot_lng, r.in_lat, r.in_lng, r.out_lat, r.out_lng,
        r.in_accuracy, r.out_accuracy, r.in_distance, r.out_distance,
        EXISTS (SELECT 1 FROM attendance_photos p WHERE p.shift_id = s.id) AS photo_available,' : '';
    return attendance_query($db, "SELECT s.id, s.staff_id, s.lot_id, s.lot_name, s.shift_date, s.starts_at, s.ends_at, $private
        r.checked_in, r.checked_out, r.corrected_in, r.corrected_out, r.in_zone, r.out_zone, r.in_capture, r.out_capture,
        r.radius_m, r.accuracy_m, r.grace_minutes, q.status AS review_status, q.kind AS review_kind,
        CASE WHEN q.status = 'pending' AND q.clarified_at IS NOT NULL AND q.replied_at IS NULL THEN 1 ELSE 0 END AS awaiting_reply,
        EXISTS (SELECT 1 FROM attendance_requests c WHERE c.shift_id = s.id AND c.kind = 'correction') AS has_correction,
        EXISTS (SELECT 1 FROM attendance_requests c WHERE c.shift_id = s.id AND c.kind = 'exception') AS has_camera_exception,
        (SELECT c.status FROM attendance_requests c WHERE c.shift_id = s.id
            AND (c.kind = 'exception' OR (c.kind = 'evidence' AND c.created_at = r.checked_in))
            ORDER BY c.id DESC LIMIT 1) AS checkin_review
        FROM attendance_shifts s LEFT JOIN attendance_records r ON r.shift_id = s.id
        LEFT JOIN attendance_requests q ON q.id = (SELECT id FROM attendance_requests WHERE shift_id = s.id ORDER BY id DESC LIMIT 1)
        WHERE $access AND ($where) ORDER BY s.starts_at DESC, s.staff_id, s.id$rowLimit", [...$owner, ...$params])->fetchAll();
}

function attendance_row(PDO $db, array $actor, int $id): array {
    $rows = attendance_rows($db, $actor, 's.id = ?', [$id]);
    return $rows[0] ?? throw new RuntimeException('Attendance record not found.', 404);
}

function attendance_times(array $row): array {
    return [$row['corrected_in'] ?? $row['checked_in'], $row['corrected_out'] ?? $row['checked_out']];
}

function attendance_punctuality(array $row): ?string {
    [$in] = attendance_times($row);
    return $in === null ? null : ($in <= $row['starts_at'] + $row['grace_minutes'] * 60 ? 'on_time' : 'late');
}

function attendance_status(array $row, int $now): string {
    [$in, $out] = attendance_times($row);
    return match (true) {
        $row['review_status'] === 'pending' => 'pending',
        $row['review_status'] === 'rejected' => 'rejected',
        $out !== null => 'checked_out',
        $in !== null => attendance_punctuality($row),
        attendance_leave($row) => 'leave',
        $now >= $row['ends_at'] => 'missing',
        default => 'scheduled',
    };
}

function attendance_duration(array $row): string {
    [$in, $out] = attendance_times($row);
    if ($in === null || $out === null) return '—';
    $minutes = intdiv(max(0, $out - $in), 60);
    return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
}

function attendance_clock(?int $time): string {
    return $time === null ? '—' : date('d M Y, H:i:s', $time);
}

function attendance_error(RuntimeException $error): string {
    $status = in_array($error->getCode(), [400, 403, 404, 409, 422, 503], true) ? $error->getCode() : 503;
    http_response_code($status);
    return $status === 503 ? 'Attendance is temporarily unavailable. Please try again.' : $error->getMessage();
}

function attendance_audit(PDO $db, array $actor, ?int $shiftId, string $action, array $before, array $after, string $reason): void {
    attendance_query($db, 'INSERT INTO attendance_audit (scope, shift_id, admin_id, action, before_value, after_value, reason, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [vehicle_account_scope(), $shiftId, $actor['id'], $action,
        json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR), $reason, time()]);
}
