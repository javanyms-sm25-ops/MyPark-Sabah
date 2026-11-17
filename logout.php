<?php
require 'includes.php';

// Keep issued compounds in this browser so a demo payer can sign in after staff.
$fineAudit = array_filter($_SESSION['staff_audit'] ?? [], fn($key) => str_starts_with($key, 'fine:'), ARRAY_FILTER_USE_KEY);
$_SESSION = [
    'staff_fines' => $_SESSION['staff_fines'] ?? [],
    'staff_counters' => ['fine' => $_SESSION['staff_counters']['fine'] ?? 0, 'maintenance' => 0],
    'staff_audit' => $fineAudit,
];
session_regenerate_id(true);
session_write_close();
header('Location: login.php');
exit;
