<?php

const MAINTENANCE_PHOTO_LIMIT = 5_000_000;
const MAINTENANCE_PHOTO_TYPES = [
    IMAGETYPE_JPEG => 'image/jpeg',
    IMAGETYPE_PNG => 'image/png',
    IMAGETYPE_WEBP => 'image/webp',
];

function maintenance_photo_upload(?array $file): ?array {
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new StaffValidationException(['photo' => 'Choose a maintenance photo up to 5 MB.']);
    }

    $path = $file['tmp_name'] ?? null;
    if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        throw new StaffValidationException(['photo' => 'The photo could not be uploaded. Choose it again.']);
    }
    // Limit the read even if the uploaded size is missing or incorrect.
    $bytes = @file_get_contents($path, false, null, 0, MAINTENANCE_PHOTO_LIMIT + 1);
    if ($bytes === false) {
        throw new StaffValidationException(['photo' => 'The photo could not be uploaded. Choose it again.']);
    }
    if (strlen($bytes) > MAINTENANCE_PHOTO_LIMIT) {
        throw new StaffValidationException(['photo' => 'Choose a maintenance photo up to 5 MB.']);
    }

    $image = @getimagesizefromstring($bytes);
    $mime = $image ? (MAINTENANCE_PHOTO_TYPES[$image[2]] ?? null) : null;
    if (!$mime || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > 32_000_000
        || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
        throw new StaffValidationException(['photo' => 'Use a valid JPG, PNG or WebP image.']);
    }
    return ['mime' => $mime, 'bytes' => $bytes];
}

function maintenance_photo_write(int $id, ?array $photo): void {
    try {
        $db = mypark_database();
        $key = [MyParkData::workspace(), $id];
        $db->prepare('DELETE FROM staff_maintenance_photo_parts WHERE workspace_id = ? AND record_id = ?')->execute($key);
        if ($photo === null) return;

        $insert = $db->prepare('INSERT INTO staff_maintenance_photo_parts (workspace_id, record_id, part_number, mime, bytes)
            VALUES (?, ?, ?, ?, ?)');
        foreach (str_split($photo['bytes'], MYPARK_DATABASE_PART_BYTES) as $part => $bytes) {
            $insert->execute([...$key, $part, $photo['mime'], $bytes]);
        }
    } catch (PDOException $error) {
        throw new RuntimeException('Maintenance photo storage is unavailable.', 503, $error);
    }
}

function maintenance_photo_read(int $id): ?array {
    $query = mypark_database()->prepare('SELECT mime, bytes FROM staff_maintenance_photo_parts
        WHERE workspace_id = ? AND record_id = ? ORDER BY part_number');
    $query->execute([MyParkData::workspace(), $id]);
    $parts = $query->fetchAll();
    if (!$parts || !in_array($parts[0]['mime'], MAINTENANCE_PHOTO_TYPES, true)) return null;
    return ['mime' => $parts[0]['mime'], 'bytes' => implode('', array_column($parts, 'bytes'))];
}
