<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/employee-attendance.php';

require_login('../pages/login.php');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'; sandbox');

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        http_response_code(405);
        exit;
    }
    [$scope, $parameters] = attendance_visibility(me());
    $shift = attendance_int($_GET['shift'] ?? null);
    $db = attendance_db();
    $bytes = attendance_query($db, "SELECT p.jpeg FROM attendance_photos p JOIN attendance_shifts s ON s.id = p.shift_id
        WHERE $scope AND s.id = ?", [...$parameters, $shift])->fetchColumn();
    if ($bytes === false) throw new RuntimeException('Attendance photo not found.', 404);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($bytes));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') echo $bytes;
} catch (RuntimeException $error) {
    header('Content-Type: text/plain; charset=utf-8');
    $message = attendance_error($error);
    echo te($message);
}
