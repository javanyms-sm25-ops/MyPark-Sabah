<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/staff-data.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; sandbox");
header('Referrer-Policy: no-referrer');

try {
    staff_actor();
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        throw new RuntimeException('Method not allowed.', 405);
    }
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $record = $id === false ? null : (MyParkData::$records['staff_maintenance'][$id] ?? null);
    // The Staff maintenance table already shares active records within the current workspace.
    if (!$record || !empty($record['deleted_at']) || empty($record['photo_version'])) {
        throw new RuntimeException('Maintenance photo not found.', 404);
    }
    $photo = maintenance_photo_read($id);
    if (!$photo) throw new RuntimeException('Maintenance photo not found.', 404);

    header('Content-Type: ' . $photo['mime']);
    header('Content-Length: ' . strlen($photo['bytes']));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') echo $photo['bytes'];
} catch (RuntimeException $error) {
    $status = in_array($error->getCode(), [401, 403, 404, 405], true) ? $error->getCode() : 503;
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo te($status === 503 ? 'Maintenance photo storage is unavailable.' : $error->getMessage());
}
