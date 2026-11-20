<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/vehicles.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (!me()) throw new RuntimeException('Please sign in again.', 401);
    require_login('../pages/login.php');
    if (me()['role'] !== 'user') throw new RuntimeException('You can only manage your own saved vehicles.', 403);
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $result = change_saved_vehicle(me()['id'], $_POST);
        $message = t($result['message']);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        throw new RuntimeException('Choose a valid vehicle action.', 405);
    }
    $state = saved_vehicle_state(me()['id']);
    echo json_encode(['ok' => true, 'message' => $message] + $state, JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException | RuntimeException $error) {
    $status = $error->getCode();
    http_response_code($status >= 400 && $status < 600 ? $status : 422);
    echo json_encode(['ok' => false, 'message' => t($error->getMessage())], JSON_THROW_ON_ERROR);
}
