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
if ($path === '/admin') {
    header('Location: /admin/', true, 308);
    exit;
}
$pages = [
    'index.php', 'login.php', 'logout.php', 'register.php', 'forgot.php',
    'dashboard.php', 'history.php', 'profile.php', 'support.php', 'ticket.php',
    'wallet.php', 'staff.php', 'admin/index.php', 'api/availability.php',
    'api/export.php', 'api/operations.php', 'api/staff.php',
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
