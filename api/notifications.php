<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (!me()) {
    http_response_code(401);
    exit('{}');
}
require_login('../pages/login.php');
if (me()['role'] === 'staff') {
    http_response_code(403);
    exit('{}');
}

$userId = me()['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'read') {
        $ids = explode(',', substr($_POST['ids'] ?? '', 0, 1500));
        foreach ($ids as $id) {
            if (isset($_SESSION['parking_notifications'][$userId][$id])) {
                $_SESSION['parking_notifications'][$userId][$id]['read'] = true;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'zone') {
        $zoneId = filter_var($_POST['lot_id'] ?? null, FILTER_VALIDATE_INT);
        if ($zoneId === false || !isset(parking_alert_zones()[$zoneId])) {
            http_response_code(422);
            exit('{}');
        }
        parking_zone_notice($userId, $zoneId);
    } else {
        http_response_code(400);
        exit('{}');
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('{}');
}

$items = parking_notification_items($userId);
$unread = array_filter($items, fn($notice) => !$notice['read']);
$statuses = array_column($_SESSION['demo_bookings'], 'status', 'id');
$now = time();
$live = array_filter($unread, fn($notice) => match ($notice['type']) {
    'ending' => $notice['expires_at'] > $now && ($statuses[$notice['booking_id']] ?? '') === 'active',
    'zone', 'cancelled' => $now - $notice['created_at'] <= 120,
    'payment' => !isset($notice['compound']) && $now - $notice['created_at'] <= 120,
    default => false,
});

$result = [
    'unread' => count($unread),
    'preview' => parking_notice_list(array_slice($items, 0, 3)),
    'live' => array_values(array_map(fn($notice) => [
        'id' => $notice['id'], 'type' => $notice['type'], 'message' => parking_notice_message($notice),
    ], $live)),
];
if (($_GET['all'] ?? '') === '1') $result['inbox'] = parking_notice_list($items, true);
echo json_encode($result, JSON_THROW_ON_ERROR);
