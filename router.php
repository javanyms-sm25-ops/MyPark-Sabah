<?php
// Run from the project directory: php -S localhost:8000 router.php
$path = rawurldecode(explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0]);
if (preg_match('~(?:^|/)(?:config|includes|storage)(?:/|$)|(?:^|/)\.|[\\\\\x00-\x1f]~i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

$page = match ($path) {
    '/' => 'index.php',
    '/admin/' => 'admin/index.php',
    default => ltrim($path, '/'),
};
if (in_array($path, ['/login.php', '/register.php'], true)) {
    header('Location: /pages' . $path, true, 308);
    exit;
}
if ($path === '/admin') {
    header('Location: /admin/', true, 308);
    exit;
}
$pages = [
    'index.php', 'pages/login.php', 'logout.php', 'pages/register.php', 'forgot.php',
    'dashboard.php', 'find_parking.php', 'parking-details.php', 'parking.php', 'history.php', 'profile.php', 'oku-verification.php', 'support.php', 'ticket.php', 'notifications.php',
    'wallet.php', 'checkout.php', 'compound.php', 'receipts.php', 'staff.php', 'admin/index.php', 'admin/attendance.php', 'api/availability.php',
    'api/export.php', 'api/operations.php', 'api/staff.php', 'api/oku-proof.php', 'api/paypal.php', 'api/vehicles.php', 'api/notifications.php',
    'staff-attendance.php', 'admin/employee-attendance.php', 'admin/add-parking-location.php', 'api/attendance-photo.php',
];
if (in_array($page, $pages, true)) {
    $_SERVER['SCRIPT_NAME'] = '/' . $page;
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/' . $page;
    require $_SERVER['SCRIPT_FILENAME'];
    return;
}
if (preg_match('~^assets/(?:css|js|images)/[\w./-]+\.(?:css|js|png|jpe?g|webp|gif|svg|ico|avif)$~i', $page)) {
    $asset = realpath(__DIR__ . '/' . $page);
    $assetRoot = realpath(__DIR__ . '/assets') . DIRECTORY_SEPARATOR;
    if ($asset !== false && str_starts_with($asset, $assetRoot) && is_file($asset)) return false;
}
http_response_code(404);
echo 'Not found';
