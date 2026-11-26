<?php

/** Open only the old databases under PHP's private session directory. */
function mypark_legacy_sqlite(string $file): ?PDO {
    $path = session_save_path() . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path) || filesize($path) === 0) return null;
    return new PDO('sqlite:' . $path, null, null, [
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function mypark_legacy_rows(PDO $source, string $sql, string $scope): PDOStatement {
    $query = $source->prepare($sql);
    $query->execute([$scope]);
    return $query;
}

function mypark_copy_legacy_row(PDO $database, string $table, array $row): void {
    // Table names below are fixed. Quote the source column names and bind every value.
    $columns = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
    $placeholders = implode(', ', array_fill(0, count($row), '?'));
    $query = $database->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
    $query->execute(array_values($row));
}

function mypark_import_legacy(PDO $database, string $oldScope, array $session): void {
    require_once __DIR__ . '/vehicles.php';
    $workspace = MyParkData::workspace();
    if ($oldScope !== '' && ($vehicles = mypark_legacy_sqlite('vehicles.sqlite3'))) {
        foreach (['member_vehicles', 'member_vehicle_preferences'] as $table) {
            $sql = 'SELECT * FROM ' . $table . ' WHERE account_scope = ?';
            if ($table === 'member_vehicles') $sql .= ' ORDER BY rowid';
            foreach (mypark_legacy_rows($vehicles, $sql, $oldScope) as $row) {
                $row['account_scope'] = $workspace;
                mypark_copy_legacy_row($database, $table, $row);
            }
        }
    }
    foreach ($session['saved_vehicles'] ?? [] as $memberId => $rows) {
        mypark_import_session_vehicles($database, (int)$memberId, $rows, $session['default_vehicle_ids'][$memberId] ?? null);
    }
    if ($oldScope !== '' && ($attendance = mypark_legacy_sqlite('attendance.sqlite3'))) {
        mypark_import_attendance($database, $attendance, $oldScope, $workspace);
    }
}

function mypark_import_session_vehicles(PDO $database, int $memberId, array $rows, ?string $oldDefault): void {
    $owner = [MyParkData::workspace(), $memberId];
    $exists = $database->prepare('SELECT 1 FROM member_vehicle_preferences WHERE account_scope = ? AND member_id = ?');
    $exists->execute($owner);
    if ($exists->fetchColumn()) return;

    $insert = $database->prepare('INSERT INTO member_vehicles (account_scope, member_id, id, plate, plate_key, name) VALUES (?, ?, ?, ?, ?, ?)');
    $idsByPlate = [];
    $default = null;
    foreach ($rows as $id => $row) {
        if (is_string($row)) $row = ['plate' => $row];
        if (!is_array($row) || !is_string($row['plate'] ?? null)) continue;
        $plate = normalize_vehicle_plate($row['plate']) ?? strtoupper(trim($row['plate']));
        $key = vehicle_plate_key($plate);
        if ($key === '') continue;
        if (!isset($idsByPlate[$key])) {
            $name = is_string($row['name'] ?? null) ? $row['name'] : '';
            $insert->execute([...$owner, (string)$id, $plate, $key, $name]);
            $idsByPlate[$key] = (string)$id;
        }
        if ((string)$id === $oldDefault) $default = $idsByPlate[$key];
    }
    $default ??= reset($idsByPlate) ?: null;
    $preferences = $database->prepare('INSERT INTO member_vehicle_preferences (account_scope, member_id, default_vehicle_id, selected_vehicle_id) VALUES (?, ?, ?, ?)');
    $preferences->execute([...$owner, $default, $default]);
}

function mypark_import_attendance(PDO $database, PDO $source, string $oldScope, string $workspace): void {
    require_once __DIR__ . '/employee-attendance.php';
    $tables = $source->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['attendance_settings', 'attendance_admin_locations', 'attendance_report_policy', 'attendance_report_holidays'] as $table) {
        if (!in_array($table, $tables, true)) continue;
        foreach (mypark_legacy_rows($source, 'SELECT * FROM ' . $table . ' WHERE scope = ?', $oldScope) as $row) {
            $row['scope'] = $workspace;
            mypark_copy_legacy_row($database, $table, $row);
        }
    }

    $shiftIds = [];
    foreach (mypark_legacy_rows($source, 'SELECT * FROM attendance_shifts WHERE scope = ? ORDER BY id', $oldScope) as $row) {
        $oldId = $row['id'];
        unset($row['id']);
        $row['scope'] = $workspace;
        mypark_copy_legacy_row($database, 'attendance_shifts', $row);
        $shiftIds[$oldId] = (int)$database->lastInsertId();
    }

    foreach (['attendance_records', 'attendance_photos', 'attendance_requests'] as $table) {
        $sql = 'SELECT row.* FROM ' . $table . ' row JOIN attendance_shifts shift ON shift.id = row.shift_id WHERE shift.scope = ?';
        if ($table === 'attendance_requests') $sql .= ' ORDER BY row.id';
        foreach (mypark_legacy_rows($source, $sql, $oldScope) as $row) {
            unset($row['id']);
            $row['shift_id'] = $shiftIds[$row['shift_id']];
            if ($table === 'attendance_photos') {
                attendance_store_photo($database, $row['shift_id'], $row['captured_at'], $row['jpeg']);
                continue;
            }
            mypark_copy_legacy_row($database, $table, $row);
        }
    }
    foreach (mypark_legacy_rows($source, 'SELECT * FROM attendance_audit WHERE scope = ? ORDER BY id', $oldScope) as $row) {
        unset($row['id']);
        $row['scope'] = $workspace;
        if ($row['shift_id'] !== null) $row['shift_id'] = $shiftIds[$row['shift_id']];
        mypark_copy_legacy_row($database, 'attendance_audit', $row);
    }
}
