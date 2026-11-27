<?php

const PARKING_LOCATION_PHOTO_LIMIT = 2_000_000;

function parking_location_photo_upload(?array $file): ?string {
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;

    $error = $file['error'] ?? null;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Photo must be under 2 MB.', 422);
    }
    $path = $file['tmp_name'] ?? null;
    if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        throw new RuntimeException('Photo upload failed. Please choose the file again.', 422);
    }
    $bytes = @file_get_contents($path, false, null, 0, PARKING_LOCATION_PHOTO_LIMIT + 1);
    if ($bytes === false) throw new RuntimeException('Photo upload failed. Please choose the file again.', 422);
    if (strlen($bytes) > PARKING_LOCATION_PHOTO_LIMIT) {
        throw new RuntimeException('Photo must be under 2 MB.', 422);
    }

    $image = @getimagesizefromstring($bytes);
    $mime = match ($image[2] ?? null) {
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
        default => null,
    };
    if ($mime === null || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > 32_000_000
        || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
        throw new RuntimeException('Use a valid JPG, PNG or WebP image.', 422);
    }
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

function parking_location_photo_src(array $lot): ?string {
    if (isset($lot['photo'])) return $lot['photo'];
    $filename = match ($lot['name']) {
        'Kota Kinabalu Waterfront' => 'kota-kinabalu-waterfront.webp',
        'Sandakan Central Parking' => 'sandakan-central-parking.webp',
        'Tawau Tanjung Market' => 'tawau-tanjung-market.webp',
        'TAR UMT Sabah' => 'tar-umt-sabah.webp',
        default => null,
    };
    return $filename === null ? null : 'assets/images/' . $filename;
}
