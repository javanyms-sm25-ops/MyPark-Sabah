<?php
require_once __DIR__ . '/staff-leave.php';

// Session-only private parking prototype. Empty stores; no seed fines or maintenance.
const STAFF_FINE_STATUSES = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'void' => 'Void'];
const STAFF_MAINTENANCE_STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed'];

class StaffValidationException extends RuntimeException {
    public function __construct(public array $fields) { parent::__construct('Please check the highlighted fields.', 422); }
}

function staff_actor(): array {
    if (!me()) throw new RuntimeException('Please sign in again.', 401);
    foreach ($_SESSION['demo_users'] as $user) {
        if ($user['id'] === me()['id'] && $user['role'] === 'staff' && !$user['blocked']) return $user;
    }
    throw new RuntimeException('This portal is available to active Staff accounts only.', 403);
}

function staff_store(): void {
    $_SESSION['staff_fines'] ??= [];
    $_SESSION['staff_maintenance'] ??= [];
    $_SESSION['staff_audit'] ??= [];
    $_SESSION['staff_counters'] ??= ['fine' => 0, 'maintenance' => 0];
    $_SESSION['staff_revision'] ??= 0;
    $_SESSION['staff_requests'] ??= [];
}

function staff_now(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format(DATE_ATOM); }
function staff_plate_key(string $plate): string { return preg_replace('/[^A-Z0-9]/', '', strtoupper($plate)); }
function staff_money(int $cents): string { return 'RM ' . number_format($cents / 100, 2); }

function staff_text(array $input, string $key, string $label, int $max, array &$errors, bool $required = true): string {
    $value = trim($input[$key] ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($required && $value === '') $errors[$key] = 'Please enter ' . strtolower($label) . '.';
    elseif ($length > $max) $errors[$key] = $label . ' must be ' . $max . ' characters or fewer.';
    return $value;
}

function staff_fine_fields(array $input): array {
    $errors = [];
    $plate = strtoupper(staff_text($input, 'plate', 'Vehicle plate', 15, $errors));
    if ($plate !== '' && !preg_match('/^[A-Z0-9][A-Z0-9 -]{1,14}$/', $plate)) $errors['plate'] = 'Enter a valid vehicle plate using letters, numbers, spaces or hyphens.';
    $violation = staff_text($input, 'violation', 'Violation type', 100, $errors);
    $notes = staff_text($input, 'notes', 'Notes', 2000, $errors, false);
    $amount = trim($input['amount'] ?? '');
    $cents = 0;
    if (!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $amount)) $errors['amount'] = 'Enter an amount from RM 0.01 to RM 99,999.99, with at most two decimal places.';
    else {
        $parts = explode('.', $amount);
        $cents = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
        if ($cents < 1) $errors['amount'] = 'Amount must be greater than RM 0.00.';
    }
    $date = $input['occurred_at'] ?? '';
    $occurred = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date, new DateTimeZone('Asia/Kuala_Lumpur'));
    if (!$occurred || $occurred->format('Y-m-d\TH:i') !== $date || $occurred->getTimestamp() > time() + 60) $errors['occurred_at'] = 'Enter a valid date and time that is not in the future.';
    if ($errors) throw new StaffValidationException($errors);
    return ['plate' => $plate, 'plate_key' => staff_plate_key($plate), 'violation' => $violation,
        'amount_cents' => $cents, 'occurred_at' => $occurred->format('Y-m-d\TH:i'),
        'occurred_ts' => $occurred->getTimestamp(), 'notes' => $notes];
}

function staff_maintenance_fields(array $input): array {
    $errors = [];
    $title = staff_text($input, 'title', 'Issue title', 120, $errors);
    $description = staff_text($input, 'description', 'Description', 2000, $errors);
    $lot = null;
    foreach ($_SESSION['demo_lots'] as $candidate) if ($candidate['id'] === (int)($input['lot_id'] ?? 0)) $lot = $candidate;
    if (!$lot) $errors['lot_id'] = 'Select an existing parking location.';
    $status = $input['status'] ?? 'open';
    if (!array_key_exists($status, STAFF_MAINTENANCE_STATUSES)) $errors['status'] = 'Select a valid maintenance status.';
    if ($errors) throw new StaffValidationException($errors);
    return ['title' => $title, 'description' => $description, 'lot_id' => $lot['id'], 'location' => $lot['name'], 'status' => $status];
}

function staff_audit(string $kind, int $id, string $action, array $actor, ?array $before, array $after, string $reason = ''): void {
    $_SESSION['staff_audit'][$kind . ':' . $id][] = ['action' => $action, 'at' => staff_now(), 'actor_id' => $actor['id'],
        'actor_name' => $actor['name'], 'reason' => $reason, 'before' => $before, 'after' => $after];
    $_SESSION['staff_revision']++;
}

function staff_change(array $actor, array $input): string {
    staff_store();
    foreach ($input as $value) if (!is_string($value) || str_contains($value, "\0")) throw new RuntimeException('Invalid request fields.', 400);
    if (empty($_SESSION['csrf']) || !isset($input['csrf']) || !hash_equals($_SESSION['csrf'], $input['csrf'])) throw new RuntimeException('Your session token has expired. Refresh and try again.', 403);
    // Recheck the actor against the current store on every write.
    $currentActor = staff_actor();
    if ($currentActor['id'] !== $actor['id']) throw new RuntimeException('Staff identity mismatch.', 403);
    $action = $input['action'] ?? '';
    if ($action === 'leave_submit') return staff_leave_submit($currentActor, $input);
    $allowed = ['fine_create', 'fine_update', 'fine_paid', 'fine_void', 'maintenance_create', 'maintenance_update', 'maintenance_delete'];
    if (!in_array($action, $allowed, true)) throw new RuntimeException('Unknown staff operation.', 400);
    $kind = str_starts_with($action, 'fine_') ? 'fine' : 'maintenance';
    $store = $kind === 'fine' ? 'staff_fines' : 'staff_maintenance';
    if (str_ends_with($action, '_create')) {
        $requestId = $input['request_id'] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) throw new RuntimeException('Invalid submission token. Reopen the form and try again.', 400);
        $requestKey = $actor['id'] . ':' . $requestId;
        $fingerprintInput = $input;
        unset($fingerprintInput['csrf']);
        ksort($fingerprintInput);
        $fingerprint = hash('sha256', json_encode($fingerprintInput, JSON_THROW_ON_ERROR));
        if (isset($_SESSION['staff_requests'][$requestKey])) {
            $previous = $_SESSION['staff_requests'][$requestKey];
            if ($previous['fingerprint'] !== $fingerprint) throw new RuntimeException('This submission was already used. Reopen the form for a new record.', 409);
            return $previous['message'];
        }
        $record = $kind === 'fine' ? staff_fine_fields($input) : staff_maintenance_fields($input);
        $id = ++$_SESSION['staff_counters'][$kind];
        $record += ['id' => $id, 'code' => ($kind === 'fine' ? 'F-' : 'M-') . str_pad((string)$id, 6, '0', STR_PAD_LEFT),
            'created_at' => staff_now(), 'updated_at' => staff_now(), 'created_by' => $actor['id'], 'created_by_name' => $actor['name'], 'version' => 1];
        if ($kind === 'fine') $record += ['status' => 'unpaid', 'paid_at' => null, 'payment_reference' => '', 'paid_amount_cents' => 0, 'voided_at' => null, 'void_reason' => ''];
        else $record['deleted_at'] = null;
        $_SESSION[$store][$id] = $record;
        staff_audit($kind, $id, 'created', $actor, null, $record);
        $message = ($kind === 'fine' ? 'Fine ' : 'Maintenance record ') . $record['code'] . ' created.';
        $_SESSION['staff_requests'][$requestKey] = ['fingerprint' => $fingerprint, 'message' => $message];
        return $message;
    }
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $before = $_SESSION[$store][$id] ?? null;
    if (!$before || !empty($before['deleted_at'])) throw new RuntimeException('Record not found.', 404);
    if ($before['created_by'] !== $actor['id']) throw new RuntimeException('You can only change records created by your Staff account.', 403);
    if ((int)($input['version'] ?? 0) !== $before['version']) throw new RuntimeException('This record has changed. Refresh it before trying again.', 409);
    $record = $before; $reason = '';
    if ($action === 'fine_update') {
        if ($before['status'] !== 'unpaid') throw new RuntimeException('Paid and void fines cannot be edited. Their financial history is retained.', 409);
        $changes = staff_fine_fields($input); $errors = [];
        $reason = staff_text($input, 'reason', 'Reason for correction', 500, $errors);
        if ($errors) throw new StaffValidationException($errors);
        $record = array_replace($record, $changes);
    } elseif ($action === 'fine_paid') {
        if ($before['status'] !== 'unpaid') throw new RuntimeException('Only unpaid fines can be marked as paid.', 409);
        $errors = []; $reference = staff_text($input, 'payment_reference', 'Payment reference', 120, $errors);
        if (($input['confirmed'] ?? '') !== '1') $errors['confirmed'] = 'Confirm that this payment has already been received.';
        if ($errors) throw new StaffValidationException($errors);
        $record = array_replace($record, ['status' => 'paid', 'paid_at' => staff_now(), 'payment_reference' => $reference, 'paid_amount_cents' => $before['amount_cents']]);
    } elseif ($action === 'fine_void') {
        if ($before['status'] === 'void') throw new RuntimeException('This fine is already void.', 409);
        $errors = []; $reason = staff_text($input, 'reason', 'Reason for voiding', 500, $errors);
        if ($errors) throw new StaffValidationException($errors);
        // Never delete financial records, erase payment evidence or imply a refund.
        $record = array_replace($record, ['status' => 'void', 'voided_at' => staff_now(), 'void_reason' => $reason]);
    } elseif ($action === 'maintenance_update') {
        $record = array_replace($record, staff_maintenance_fields($input));
    } elseif ($action === 'maintenance_delete') {
        $errors = []; $reason = staff_text($input, 'reason', 'Reason for removal', 500, $errors);
        if ($errors) throw new StaffValidationException($errors);
        $record['deleted_at'] = staff_now();
    }
    $record['version']++; $record['updated_at'] = staff_now();
    $_SESSION[$store][$id] = $record;
    staff_audit($kind, $id, substr($action, strlen($kind) + 1), $actor, $before, $record, $reason);
    return $record['code'] . match ($action) { 'fine_paid' => ' payment recorded.', 'fine_void' => ' voided. Audit history retained.', 'maintenance_delete' => ' removed. Audit history retained.', default => ' updated.' };
}

function staff_snapshot(array $actor): array {
    staff_store();
    $fines = array_values($_SESSION['staff_fines']);
    $maintenance = array_values(array_filter($_SESSION['staff_maintenance'], static fn($r) => !$r['deleted_at']));
    $withHistory = static function ($records, $kind) use ($actor) {
        return array_map(static function ($record) use ($kind, $actor) {
            $record['can_manage'] = $record['created_by'] === $actor['id'];
            $record['audit'] = $_SESSION['staff_audit'][$kind . ':' . $record['id']] ?? [];
            return $record;
        }, $records);
    };
    $unpaid = array_filter($fines, static fn($r) => $r['status'] === 'unpaid');
    return ['fines' => $withHistory($fines, 'fine'), 'maintenance' => $withHistory($maintenance, 'maintenance'),
        'bookings' => array_values(array_map(static fn($b) => ['id' => $b['id'], 'plate' => $b['plate'], 'plate_key' => staff_plate_key($b['plate']), 'location' => $b['lot'], 'hours' => $b['hours'], 'status' => $b['status'], 'created_at' => $b['created_at']], $_SESSION['demo_bookings'])),
        'locations' => array_values(array_map(static fn($l) => ['id' => $l['id'], 'name' => $l['name']], $_SESSION['demo_lots'])),
        'fine_statuses' => STAFF_FINE_STATUSES, 'maintenance_statuses' => STAFF_MAINTENANCE_STATUSES,
        'csrf' => csrf(), 'revision' => $_SESSION['staff_revision'],
        'summary' => ['total_fines' => count($fines), 'unpaid_fines' => count($unpaid),
            'outstanding_cents' => array_sum(array_column($unpaid, 'amount_cents')),
            'open_maintenance' => count(array_filter($maintenance, static fn($r) => $r['status'] !== 'completed'))]];
}

function staff_json(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR); exit;
}
