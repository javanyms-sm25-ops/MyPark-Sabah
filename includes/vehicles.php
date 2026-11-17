<?php

function saved_vehicles(int $userId): array {
    $vehicles = $_SESSION['saved_vehicles'][$userId] ?? [];
    foreach ($vehicles as &$vehicle) {
        // Older sessions stored just the plate string.
        if (is_string($vehicle)) $vehicle = ['plate' => $vehicle, 'name' => ''];
    }
    unset($vehicle);
    return $vehicles;
}

function default_vehicle_id(int $userId): ?string {
    return $_SESSION['default_vehicle_ids'][$userId] ?? null;
}

function normalize_vehicle_plate(string $value): ?string {
    $plate = strtoupper(trim($value));
    if (!preg_match('/^([A-Z]{1,3}) ?(\d{1,4}) ?([A-Z]?)$/D', $plate, $parts)) {
        return null;
    }
    return $parts[1] . ' ' . $parts[2] . $parts[3];
}

/** @return array{id: string, message: string} */
function change_saved_vehicle(int $userId, array $input): array {
    $vehicles = saved_vehicles($userId);
    $action = $input['vehicle_action'] ?? '';
    $id = $input['vehicle_id'] ?? '';

    if (!in_array($action, ['add', 'edit', 'delete', 'default'], true)) {
        throw new InvalidArgumentException('Choose a valid vehicle action.');
    }
    if ($action !== 'add' && !isset($vehicles[$id])) {
        throw new InvalidArgumentException('Vehicle not found.');
    }

    if ($action === 'default') {
        $_SESSION['default_vehicle_ids'][$userId] = $id;
        return ['id' => $id, 'message' => 'Default vehicle updated.'];
    }
    if ($action === 'delete') {
        unset($_SESSION['saved_vehicles'][$userId][$id]);
        if (default_vehicle_id($userId) === $id) {
            unset($_SESSION['default_vehicle_ids'][$userId]);
        }
        return ['id' => $id, 'message' => 'Vehicle removed.'];
    }

    $plate = normalize_vehicle_plate($input['plate'] ?? '');
    if ($plate === null) {
        throw new InvalidArgumentException('Enter a valid plate number, for example SAB 1234.');
    }
    $name = trim($input['vehicle_name'] ?? '');
    $nameLength = preg_match_all('/./us', $name);
    if ($nameLength === false || $nameLength > 40 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
        throw new InvalidArgumentException('Enter a vehicle name of up to 40 characters.');
    }
    foreach ($vehicles as $vehicleId => $vehicle) {
        if ((string)$vehicleId !== $id && normalize_vehicle_plate($vehicle['plate']) === $plate) {
            throw new InvalidArgumentException('This plate is already saved.');
        }
    }

    if ($action === 'add') {
        $id = 'v-' . bin2hex(random_bytes(8));
        if (!$vehicles) {
            $_SESSION['default_vehicle_ids'][$userId] = $id;
        }
    }
    $_SESSION['saved_vehicles'][$userId][$id] = ['plate' => $plate, 'name' => $name];
    return ['id' => $id, 'message' => $action === 'add' ? 'Vehicle added.' : 'Vehicle updated.'];
}
