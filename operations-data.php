<?php
// Operations uses the existing session store. No database or SQL is introduced.
const OPS_ROLES = ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff', 'user' => 'Member'];
const OPS_TRANSITIONS = ['active' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => []];

function ops_actor(): array {
    if (!me()) throw new RuntimeException('Please sign in again.', 401);
    foreach ($_SESSION['demo_users'] as $user) {
        if ($user['id'] === me()['id'] && !$user['blocked'] && in_array($user['role'], ['staff', 'admin', 'superadmin'], true)) return $user;
    }
    throw new RuntimeException('You do not have permission to access Operations.', 403);
}

function ops_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

function ops_valid_csrf(array $input): void {
    $token = $input['csrf'] ?? null;
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new RuntimeException('Your session token has expired. Refresh the page and try again.', 403);
    }
}

function ops_snapshot(array $actor): array {
    $accounts = array_map(static fn($user) => [
        'id' => (int)$user['id'], 'name' => (string)$user['name'], 'email' => (string)$user['email'],
        'role' => $user['role'], 'role_label' => OPS_ROLES[$user['role']] ?? $user['role'],
        'status' => $user['blocked'] ? 'blocked' : 'active'
    ], $_SESSION['demo_users']);
    $reservations = array_map(static function ($booking) {
        $created = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $booking['created_at'] ?? '');
        return [
            'id' => (int)$booking['id'], 'user' => (string)$booking['user'], 'lot' => (string)$booking['lot'],
            'lot_id' => (int)$booking['lot_id'], 'city' => (string)($booking['city'] ?? ''),
            'plate' => (string)$booking['plate'], 'slot' => (string)($booking['slot'] ?? ''),
            'hours' => (int)$booking['hours'], 'fee' => (float)$booking['fee'], 'status' => $booking['status'],
            'created_at' => $booking['created_at'] ?? '', 'created_ts' => $created ? $created->getTimestamp() : null,
            'created_day' => $created ? $created->format('Y-m-d') : '',
            'allowed_statuses' => OPS_TRANSITIONS[$booking['status']] ?? []
        ];
    }, $_SESSION['demo_bookings']);
    return [
        'accounts' => array_values($accounts), 'reservations' => array_values($reservations),
        'roles' => OPS_ROLES, 'reservation_statuses' => array_keys(OPS_TRANSITIONS),
        'account_statuses' => ['active', 'blocked'], 'csrf' => csrf(), 'actor_id' => $actor['id'],
        'can_manage_accounts' => $actor['role'] === 'superadmin',
        'can_export' => in_array($actor['role'], ['admin', 'superadmin'], true),
        'revision' => $_SESSION['ops_revision'] ?? 0,
        'summary' => [
            'lots' => count($_SESSION['demo_lots']), 'reservations' => count($reservations),
            'active' => count(array_filter($reservations, static fn($b) => $b['status'] === 'active')),
            'completed' => count(array_filter($reservations, static fn($b) => $b['status'] === 'completed')),
            'cancelled' => count(array_filter($reservations, static fn($b) => $b['status'] === 'cancelled')),
            'revenue' => array_sum(array_map(static fn($b) => in_array($b['status'], ['active', 'completed'], true) ? $b['fee'] : 0, $reservations))
        ]
    ];
}

function ops_mutate(array $actor, array $input): string {
    ops_valid_csrf($input);
    $action = $input['action'] ?? '';
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'status') {
        foreach ($_SESSION['demo_bookings'] as &$booking) {
            if ($booking['id'] !== $id) continue;
            $next = $input['status'] ?? '';
            if (($input['expected_status'] ?? '') !== $booking['status']) throw new RuntimeException('This reservation has changed. Refresh and try again.', 409);
            if (!in_array($next, OPS_TRANSITIONS[$booking['status']] ?? [], true)) throw new RuntimeException('This reservation status transition is not allowed.', 422);
            $booking['status'] = $next;
            $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
            return 'Reservation #' . $id . ' updated.';
        }
        unset($booking);
        throw new RuntimeException('Reservation not found.', 404);
    }
    if ($action === 'toggle_user') {
        if ($actor['role'] !== 'superadmin') throw new RuntimeException('Only Super Admin can block or unblock accounts.', 403);
        foreach ($_SESSION['demo_users'] as &$user) {
            if ($user['id'] !== $id) continue;
            $current = $user['blocked'] ? 'blocked' : 'active';
            $next = $input['status'] ?? '';
            if (($input['expected_status'] ?? '') !== $current) throw new RuntimeException('This account has changed. Refresh and try again.', 409);
            if (!in_array($next, ['active', 'blocked'], true) || $next === $current) throw new RuntimeException('Invalid account status change.', 422);
            if ($next === 'blocked') {
                if ($user['id'] === $actor['id']) throw new RuntimeException('You cannot block your own account.', 422);
                $activeSuperAdmins = count(array_filter($_SESSION['demo_users'], static fn($u) => $u['role'] === 'superadmin' && !$u['blocked']));
                if ($user['role'] === 'superadmin' && $activeSuperAdmins <= 1) throw new RuntimeException('The last active Super Admin cannot be blocked.', 422);
            }
            $user['blocked'] = $next === 'blocked';
            $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
            return 'Account ' . ($user['blocked'] ? 'blocked.' : 'unblocked.');
        }
        unset($user);
        throw new RuntimeException('Account not found.', 404);
    }
    if ($action === 'add_lot') {
        if (!in_array($actor['role'], ['admin', 'superadmin'], true)) throw new RuntimeException('Only an administrator can add locations.', 403);
        $name = trim($input['name'] ?? ''); $city = trim($input['city'] ?? ''); $address = trim($input['address'] ?? '');
        $rate = filter_var($input['rate'] ?? null, FILTER_VALIDATE_FLOAT);
        $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || $city === '' || $address === '' || $rate === false || !is_finite((float)$rate) || $rate < 0.5 || $capacity === false || $capacity < 1) throw new RuntimeException('Enter a location name, city, address, valid rate and capacity.', 422);
        $_SESSION['demo_lots'][] = ['id' => max(array_column($_SESSION['demo_lots'], 'id') ?: [0]) + 1, 'name' => $name, 'city' => $city, 'address' => $address, 'rate' => $rate, 'capacity' => $capacity, 'lat' => 5.98, 'lng' => 116.07];
        $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
        return 'Parking location added.';
    }
    throw new RuntimeException('Unknown operation.', 400);
}

// Export applies the same filter -> typed sort pipeline to the full authorized set.
function ops_filter_sort(array $rows, string $table, array $query): array {
    $textKeys = $table === 'accounts' ? ['name', 'email'] : ['user', 'plate', 'lot'];
    $q = trim(is_string($query['q'] ?? null) ? $query['q'] : '');
    $fold = static fn($value) => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $rows = array_values(array_filter($rows, static function ($row) use ($query, $q, $fold, $textKeys, $table) {
        if ($q !== '' && !str_contains($fold(implode(' ', array_map(static fn($key) => $row[$key], $textKeys))), $fold($q))) return false;
        foreach (['status', 'role'] as $key) if (!empty($query[$key]) && ($row[$key] ?? '') !== $query[$key]) return false;
        if ($table === 'reservations') {
            if (!empty($query['location']) && (string)$row['lot_id'] !== (string)$query['location']) return false;
            if (!empty($query['from']) && (!$row['created_day'] || $row['created_day'] < $query['from'])) return false;
            if (!empty($query['to']) && (!$row['created_day'] || $row['created_day'] > $query['to'])) return false;
        }
        return true;
    }));
    $allowed = $table === 'accounts' ? ['id', 'name', 'email', 'role_label', 'status'] : ['id', 'user', 'lot', 'plate', 'fee', 'hours', 'status', 'created_ts'];
    $sort = in_array($query['sort'] ?? '', $allowed, true) ? $query['sort'] : 'id';
    $direction = ($query['dir'] ?? 'desc') === 'asc' ? 1 : -1;
    usort($rows, static function ($a, $b) use ($sort, $direction) {
        $left = $a[$sort]; $right = $b[$sort];
        if ($left === null || $right === null) return $left === $right ? $a['id'] <=> $b['id'] : ($left === null ? 1 : -1);
        $order = in_array($sort, ['id', 'fee', 'hours', 'created_ts'], true) ? $left <=> $right : strnatcasecmp($left, $right);
        return $order ? $direction * $order : $a['id'] <=> $b['id'];
    });
    return $rows;
}

function ops_csv_cell($value): string {
    $value = (string)$value;
    return preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $value) ? "'" . $value : $value;
}
