<?php

function normalize_vehicle_plate(string $value): ?string {
    $compact = preg_replace('/\\s+/u', '', strtoupper(trim($value)));
    if ($compact === null || !preg_match('/^([A-Z]{1,3})(\\d{1,4})([A-Z]?)$/D', $compact, $parts)) return null;
    return $parts[1] . ' ' . $parts[2] . $parts[3];
}

function vehicle_plate_key(string $plate): string {
    return preg_replace('/\\s+/u', '', strtoupper(trim($plate))) ?? '';
}

/** Demo account IDs are browser-specific, so database ownership uses the same scope. */
function vehicle_account_scope(): string {
    $_SESSION['vehicle_account_scope'] ??= bin2hex(random_bytes(24));
    return $_SESSION['vehicle_account_scope'];
}

function vehicle_database(): PDO {
    static $database = null;
    if ($database !== null) return $database;
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Saved vehicles are unavailable. Please try again later.', 503);
    }
    try {
        // start_demo_session() creates this private directory outside the web root.
        $database = new PDO('sqlite:' . session_save_path() . DIRECTORY_SEPARATOR . 'vehicles.sqlite3');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $database->exec('PRAGMA busy_timeout = 5000');
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec('CREATE TABLE IF NOT EXISTS member_vehicles (
            account_scope TEXT NOT NULL,
            member_id INTEGER NOT NULL,
            id TEXT NOT NULL,
            plate TEXT NOT NULL,
            plate_key TEXT NOT NULL,
            name TEXT NOT NULL DEFAULT \'\',
            PRIMARY KEY (account_scope, member_id, id),
            UNIQUE (account_scope, member_id, plate_key)
        )');
        $database->exec('CREATE TABLE IF NOT EXISTS member_vehicle_preferences (
            account_scope TEXT NOT NULL,
            member_id INTEGER NOT NULL,
            default_vehicle_id TEXT,
            selected_vehicle_id TEXT,
            PRIMARY KEY (account_scope, member_id),
            FOREIGN KEY (account_scope, member_id, default_vehicle_id)
                REFERENCES member_vehicles (account_scope, member_id, id),
            FOREIGN KEY (account_scope, member_id, selected_vehicle_id)
                REFERENCES member_vehicles (account_scope, member_id, id)
        )');
    } catch (PDOException $error) {
        $database = null;
        throw new RuntimeException('Saved vehicles are unavailable. Please try again later.', 503, $error);
    }
    return $database;
}

/** Import existing session vehicles once, retaining their IDs and default. */
function vehicle_store(int $userId): PDO {
    if (!me() || me()['role'] !== 'user' || me()['id'] !== $userId) {
        throw new RuntimeException('You can only manage your own saved vehicles.', 403);
    }
    $database = vehicle_database();
    $scope = vehicle_account_scope();
    $query = $database->prepare('SELECT 1 FROM member_vehicle_preferences WHERE account_scope = ? AND member_id = ?');
    $query->execute([$scope, $userId]);
    if ($query->fetchColumn()) return $database;

    $database->beginTransaction();
    try {
        $insert = $database->prepare('INSERT INTO member_vehicles (account_scope, member_id, id, plate, plate_key, name) VALUES (?, ?, ?, ?, ?, ?)');
        $imported = [];
        $aliases = [];
        foreach ($_SESSION['saved_vehicles'][$userId] ?? [] as $id => $vehicle) {
            if (is_string($vehicle)) $vehicle = ['plate' => $vehicle, 'name' => ''];
            if (!is_array($vehicle) || !is_string($vehicle['plate'] ?? null)) continue;
            $plate = normalize_vehicle_plate($vehicle['plate']) ?? strtoupper(trim($vehicle['plate']));
            $key = vehicle_plate_key($plate);
            if ($key === '') continue;
            if (isset($imported[$key])) {
                $aliases[(string)$id] = $imported[$key];
                continue;
            }
            $name = is_string($vehicle['name'] ?? null) ? $vehicle['name'] : '';
            $insert->execute([$scope, $userId, (string)$id, $plate, $key, $name]);
            $imported[$key] = (string)$id;
            $aliases[(string)$id] = (string)$id;
        }
        $default = $aliases[$_SESSION['default_vehicle_ids'][$userId] ?? ''] ?? (array_values($imported)[0] ?? null);
        $preferences = $database->prepare('INSERT INTO member_vehicle_preferences (account_scope, member_id, default_vehicle_id, selected_vehicle_id) VALUES (?, ?, ?, ?)');
        $preferences->execute([$scope, $userId, $default, $default]);
        $database->commit();
        unset($_SESSION['saved_vehicles'][$userId], $_SESSION['default_vehicle_ids'][$userId]);
    } catch (Throwable $error) {
        $database->rollBack();
        throw new RuntimeException('Saved vehicles are unavailable. Please try again later.', 503, $error);
    }
    return $database;
}

function vehicle_snapshot(PDO $database, int $userId): array {
    $owner = [vehicle_account_scope(), $userId];
    $query = $database->prepare('SELECT id, plate, name FROM member_vehicles WHERE account_scope = ? AND member_id = ? ORDER BY rowid');
    $query->execute($owner);
    $vehicles = [];
    foreach ($query as $vehicle) $vehicles[$vehicle['id']] = ['plate' => $vehicle['plate'], 'name' => $vehicle['name']];
    $query = $database->prepare('SELECT default_vehicle_id, selected_vehicle_id FROM member_vehicle_preferences WHERE account_scope = ? AND member_id = ?');
    $query->execute($owner);
    $preferences = $query->fetch();
    $default = $preferences['default_vehicle_id'] ?? null;
    if (!isset($vehicles[$default ?? ''])) $default = $vehicles ? (string)array_key_first($vehicles) : null;
    $selected = $preferences['selected_vehicle_id'] ?? null;
    if (!isset($vehicles[$selected ?? ''])) $selected = $default;
    return ['vehicles' => $vehicles, 'default_id' => $default, 'selected_id' => $selected];
}

function saved_vehicle_state(int $userId): array {
    try {
        return vehicle_snapshot(vehicle_store($userId), $userId);
    } catch (PDOException $error) {
        throw new RuntimeException('Saved vehicles are unavailable. Please try again later.', 503, $error);
    }
}

function saved_vehicles(int $userId): array {
    return saved_vehicle_state($userId)['vehicles'];
}

function default_vehicle_id(int $userId): ?string {
    return saved_vehicle_state($userId)['default_id'];
}

function vehicle_has_active_booking(int $userId, string $id, string $plate): bool {
    complete_expired_bookings();
    foreach ($_SESSION['demo_bookings'] as $booking) {
        if ($booking['user_id'] !== $userId || $booking['status'] !== 'active') continue;
        if (($booking['vehicle_id'] ?? null) === $id || vehicle_plate_key($booking['plate']) === vehicle_plate_key($plate)) return true;
    }
    return false;
}

/** @return array{id: string, message: string} */
function change_saved_vehicle(int $userId, array $input): array {
    $action = $input['vehicle_action'] ?? '';
    $id = $input['vehicle_id'] ?? '';
    if (!is_string($action) || !in_array($action, ['add', 'edit', 'delete', 'default', 'select'], true) || !is_string($id)) {
        throw new InvalidArgumentException('Choose a valid vehicle action.', 422);
    }
    try {
        $database = vehicle_store($userId);
        $database->beginTransaction();
        $state = vehicle_snapshot($database, $userId);
        $vehicles = $state['vehicles'];
        if ($action !== 'add' && !isset($vehicles[$id])) throw new InvalidArgumentException('Vehicle not found.', 404);
        $owner = [vehicle_account_scope(), $userId];
        $default = $state['default_id'];
        $selected = $state['selected_id'];

        if ($action === 'default' || $action === 'select') {
            if ($action === 'default') $default = $id;
            else $selected = $id;
            $message = $action === 'default' ? 'Default vehicle updated.' : 'Selected vehicle updated.';
        } elseif ($action === 'delete') {
            if (vehicle_has_active_booking($userId, $id, $vehicles[$id]['plate'])) {
                throw new InvalidArgumentException('End this vehicle’s active parking session before removing it.', 409);
            }
            unset($vehicles[$id]);
            if ($default === $id) $default = $vehicles ? (string)array_key_first($vehicles) : null;
            if ($selected === $id) $selected = $default;
            $message = 'Vehicle removed.';
        } else {
            $plate = is_string($input['plate'] ?? null) ? normalize_vehicle_plate($input['plate']) : null;
            if ($plate === null) throw new InvalidArgumentException('Enter a valid plate number, for example SAB 1234.', 422);
            $name = is_string($input['vehicle_name'] ?? '') ? trim($input['vehicle_name'] ?? '') : '';
            $nameLength = preg_match_all('/./us', $name);
            if ($nameLength === false || $nameLength > 40 || preg_match('/[\\x00-\\x1F\\x7F]/', $name)) {
                throw new InvalidArgumentException('Enter a vehicle name of up to 40 characters.', 422);
            }
            foreach ($vehicles as $vehicleId => $vehicle) {
                if (!($action === 'edit' && (string)$vehicleId === $id) && vehicle_plate_key($vehicle['plate']) === vehicle_plate_key($plate)) {
                    throw new InvalidArgumentException('This plate is already saved.', 422);
                }
            }
            if ($action === 'edit' && vehicle_plate_key($vehicles[$id]['plate']) !== vehicle_plate_key($plate)
                && vehicle_has_active_booking($userId, $id, $vehicles[$id]['plate'])) {
                throw new InvalidArgumentException('End this vehicle’s active parking session before changing its plate.', 409);
            }
            if ($action === 'add') {
                $id = 'v-' . bin2hex(random_bytes(12));
                $query = $database->prepare('INSERT INTO member_vehicles (account_scope, member_id, id, plate, plate_key, name) VALUES (?, ?, ?, ?, ?, ?)');
                $query->execute([...$owner, $id, $plate, vehicle_plate_key($plate), $name]);
                $default ??= $id;
                $selected ??= $id;
            } else {
                $query = $database->prepare('UPDATE member_vehicles SET plate = ?, plate_key = ?, name = ? WHERE account_scope = ? AND member_id = ? AND id = ?');
                $query->execute([$plate, vehicle_plate_key($plate), $name, ...$owner, $id]);
            }
            $message = $action === 'add' ? 'Vehicle added.' : 'Vehicle updated.';
        }
        $query = $database->prepare('UPDATE member_vehicle_preferences SET default_vehicle_id = ?, selected_vehicle_id = ? WHERE account_scope = ? AND member_id = ?');
        $query->execute([$default, $selected, ...$owner]);
        if ($action === 'delete') {
            $query = $database->prepare('DELETE FROM member_vehicles WHERE account_scope = ? AND member_id = ? AND id = ?');
            $query->execute([...$owner, $id]);
        }
        $database->commit();
        return ['id' => $id, 'message' => $message];
    } catch (Throwable $error) {
        if (isset($database) && $database->inTransaction()) $database->rollBack();
        if ($error instanceof PDOException) throw new RuntimeException('Saved vehicles are unavailable. Please try again later.', 503, $error);
        throw $error;
    }
}
