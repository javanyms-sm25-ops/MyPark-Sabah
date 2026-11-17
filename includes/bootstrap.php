<?php
require_once __DIR__ . '/demo-session.php';
start_demo_session();
date_default_timezone_set('Asia/Kuala_Lumpur');
// Front-end prototype store. Demo data lives in the session; no database connection is made.
$_SESSION['demo_users'] ??= [
 ['id'=>1,'name'=>'Super Admin','email'=>'superadmin@park.com','password'=>password_hash('super123',PASSWORD_DEFAULT),'role'=>'superadmin','blocked'=>false],
 ['id'=>2,'name'=>'Parking Admin','email'=>'admin@park.com','password'=>password_hash('admin123',PASSWORD_DEFAULT),'role'=>'admin','blocked'=>false],
 ['id'=>3,'name'=>'Parking Staff','email'=>'staff@park.com','password'=>password_hash('staff123',PASSWORD_DEFAULT),'role'=>'staff','blocked'=>false],
 ['id'=>4,'name'=>'Demo Member','email'=>'user@park.com','password'=>password_hash('user123',PASSWORD_DEFAULT),'role'=>'user','blocked'=>false]
];
$_SESSION['demo_lots'] ??= [
 ['id'=>1,'name'=>'Kota Kinabalu Waterfront','city'=>'Kota Kinabalu','address'=>'Jalan Tun Fuad Stephens','rate'=>3.00,'capacity'=>36,'lat'=>5.9804,'lng'=>116.0735],
 ['id'=>2,'name'=>'Sandakan Central Parking','city'=>'Sandakan','address'=>'Bandar Sandakan','rate'=>2.00,'capacity'=>24,'lat'=>5.8402,'lng'=>118.1179],
 ['id'=>3,'name'=>'Tawau Town Centre','city'=>'Tawau','address'=>'Jalan Dunlop','rate'=>2.50,'capacity'=>30,'lat'=>4.2498,'lng'=>117.8871]
];
$_SESSION['demo_bookings'] ??= [];
complete_expired_bookings();
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function csrf(): string {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf(){
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403); exit('Session expired. Refresh the page and try again.');
    }
    // These forms accept scalar fields only. Reject crafted arrays before string/number operations.
    foreach ($_POST as $value) if (!is_string($value) || str_contains($value, "\0")) {
        http_response_code(400); exit('Invalid request fields.');
    }
}
function me(){return $_SESSION['user']??null;}
function &demo_wallet(): array {
    $userId = me()['id'];
    $_SESSION['demo_wallets'][$userId] ??= [
        'balance' => $_SESSION['demo_wallet_balance'] ?? 30.00,
        'transactions' => $_SESSION['demo_wallet_transactions'] ?? [],
    ];
    unset($_SESSION['demo_wallet_balance'], $_SESSION['demo_wallet_transactions']);
    return $_SESSION['demo_wallets'][$userId];
}
function require_login(string $loginPath = 'pages/login.php'){
    if (!me()) { header('Location: ' . $loginPath); exit; }
    foreach ($_SESSION['demo_users'] as $account) {
        if ($account['id'] === me()['id'] && !$account['blocked'] && $account['role'] === me()['role']) return;
    }
    unset($_SESSION['user']);
    http_response_code(403); exit('Your account is no longer available. Please sign in again.');
}
function booking_expiry(array $booking): ?int {
    $expiry = filter_var($booking['expires_at'] ?? null, FILTER_VALIDATE_INT);
    if ($expiry !== false && $expiry > 0) return $expiry;

    // Older reservations use their original start time rather than a new countdown.
    $created = $booking['created_at'] ?? '';
    $hours = filter_var($booking['hours'] ?? null, FILTER_VALIDATE_INT);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $created);
    if (!$start || $start->format('Y-m-d H:i') !== $created || $hours === false || $hours < 1 || $hours > 24) return null;
    return $start->getTimestamp() + $hours * 3600;
}
function complete_expired_bookings(): void {
    $now = time();
    $changed = false;
    foreach ($_SESSION['demo_bookings'] as &$booking) {
        if ($booking['status'] !== 'active') continue;
        $expiry = booking_expiry($booking);
        if ($expiry === null || $expiry > $now) continue;
        $booking['status'] = 'completed';
        $changed = true;
    }
    unset($booking);
    if ($changed) $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
}
function cancel_booking(int $id, int $userId): string {
    complete_expired_bookings();
    foreach ($_SESSION['demo_bookings'] as &$booking) {
        if ($booking['id'] !== $id || $booking['user_id'] !== $userId) continue;
        if ($booking['status'] !== 'active') return 'Only active bookings can be cancelled.';
        $booking['status'] = 'cancelled';
        $_SESSION['ops_revision'] = ($_SESSION['ops_revision'] ?? 0) + 1;
        return 'Booking cancelled.';
    }
    return 'Booking not found.';
}
function require_admin(){require_login();if(!in_array(me()['role'],['admin','superadmin'],true)){http_response_code(403);exit('Forbidden');}}
function flash(?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $message;
}
function head($title){if((me()['role']??'')==='staff'){require_once __DIR__ . '/staff-layout.php';staff_head($title);return;}$u=me();$f=flash();$notice=$u?count(array_filter($_SESSION['demo_bookings']??[],fn($b)=>$b['user_id']===$u['id']&&$b['status']==='active')):0;echo '<!doctype html><html lang="en"><head><meta charset="utf-8">' . ((defined('MYPARK_ADMIN_PAGE') || defined('MYPARK_AUTH_PAGE')) ? '<base href="../">' : '') . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' - MyPark Sabah</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><script src="assets/js/loading.js"></script><link rel="stylesheet" href="assets/css/style.css"></head><body><header><a class="logo" href="index.php">🅿 MyPark Sabah</a><nav aria-label="Main menu">';if($u){if($u['role']==='superadmin'){echo '<a href="admin/">Operations</a>';}else{echo '<a href="dashboard.php">Parking</a><a href="history.php">History</a><a href="receipts.php">Receipts</a><a href="wallet.php">Wallet</a><a href="compound.php">Compounds</a><a href="profile.php">Profile</a><a href="support.php">Support</a>';if(in_array($u['role'],['admin','staff'],true))echo '<a href="admin/">Operations</a>';echo '<a class="notification-link" href="history.php" aria-label="Parking notifications">🔔'.($notice?'<b>'.$notice.'</b>':'').'</a>';}echo '<span>'.e($u['name']).' · '.e($u['role']).'</span><a href="logout.php">Logout</a>';}else echo '<a href="pages/login.php">Login</a><a href="pages/register.php">Register</a>';echo '<button id="themeBtn" class="ghost" type="button">🌓</button></nav></header><main>';if($f)echo '<div class="flash">'.e($f).'</div>';}
function foot(bool $showChatbot = true){if((me()['role']??'')==='staff'){require_once __DIR__ . '/staff-layout.php';staff_foot($showChatbot);return;}echo '</main><footer><section><b>MyPark Sabah</b><p>Simple parking reservations across Sabah.</p></section><section><b>Contact</b><p>support@mypark-sabah.demo<br>+60 88 000 000</p></section><section><b>Help</b><p><a href="support.php">Customer support</a><br><a href="profile.php">My profile</a></p></section></footer><div id="toast-stack" class="pe-toast-stack" role="region" aria-label="Notifications"></div><div id="toast-status" class="pe-toast-announcer" role="status" aria-live="polite" aria-atomic="true"></div><div id="toast-alert" class="pe-toast-announcer" role="alert" aria-live="assertive" aria-atomic="true"></div><script src="assets/js/toasts.js"></script><script src="assets/js/app.js"></script> ' . ($showChatbot ? '<script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script><script src="https://files.bpcontent.cloud/2026/11/13/14/20261113143638-NZ5LEIAP.js" defer></script>' : '') . '</body></html>';}
