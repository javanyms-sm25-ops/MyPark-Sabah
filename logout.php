<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/receipt-data.php';

if (me()) demo_wallet();
receipt_sync();

// Keep issued compounds in this browser so a demo payer can sign in after staff.
$fineAudit = array_filter($_SESSION['staff_audit'] ?? [], fn($key) => str_starts_with($key, 'fine:'), ARRAY_FILTER_USE_KEY);
$_SESSION = [
    'demo_users' => $_SESSION['demo_users'],
    'demo_lots' => $_SESSION['demo_lots'],
    'demo_receipts' => $_SESSION['demo_receipts'],
    'saved_vehicles' => $_SESSION['saved_vehicles'] ?? [],
    'default_vehicle_ids' => $_SESSION['default_vehicle_ids'] ?? [],
    'oku_submissions' => $_SESSION['oku_submissions'] ?? [],
    'oku_latest' => $_SESSION['oku_latest'] ?? [],
    'demo_cookie_refreshed_at' => time(),
    'staff_fines' => $_SESSION['staff_fines'] ?? [],
    'staff_counters' => ['fine' => $_SESSION['staff_counters']['fine'] ?? 0, 'maintenance' => 0],
    'staff_audit' => $fineAudit,
];
session_regenerate_id(true);
session_write_close();
header('Location: pages/login.php');
exit;
