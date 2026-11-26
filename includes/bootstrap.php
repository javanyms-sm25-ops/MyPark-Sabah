<?php
require_once __DIR__ . '/demo-session.php';
start_demo_session();
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/parking-pricing.php';
require_once __DIR__ . '/parking-capacity.php';
require_once __DIR__ . '/parking-notifications.php';
require_once __DIR__ . '/ajax-table.php';
remember_selected_language();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once __DIR__ . '/database.php';
MyParkData::start();

// Create the existing demonstration accounts and locations once per workspace.
MyParkData::$records['demo_users'] ??= [
 ['id'=>1,'name'=>'Super Admin','email'=>'superadmin@park.com','password'=>password_hash('super123',PASSWORD_DEFAULT),'role'=>'superadmin','blocked'=>false],
 ['id'=>2,'name'=>'Parking Admin','email'=>'admin@park.com','password'=>password_hash('admin123',PASSWORD_DEFAULT),'role'=>'admin','blocked'=>false],
 ['id'=>3,'name'=>'Parking Staff','email'=>'staff@park.com','password'=>password_hash('staff123',PASSWORD_DEFAULT),'role'=>'staff','blocked'=>false],
 ['id'=>4,'name'=>'Demo Member','email'=>'user@park.com','password'=>password_hash('user123',PASSWORD_DEFAULT),'role'=>'user','blocked'=>false]
];
$tawauMarket = [
    'id' => 3,
    'name' => 'Tawau Tanjung Market',
    'city' => 'Tawau',
    'address' => 'Jalan Dunlop, Bandar Tawau, 91000',
    'lat' => 4.2434983,
    'lng' => 117.8847606,
];
MyParkData::$records['demo_lots'] ??= [
 ['id'=>1,'name'=>'Kota Kinabalu Waterfront','city'=>'Kota Kinabalu','address'=>'Jalan Tun Fuad Stephens','rate'=>PARKING_HOURLY_CENTS / 100,'capacity'=>36,'oku_capacity'=>1,'lat'=>5.9804,'lng'=>116.0735],
 ['id'=>2,'name'=>'Sandakan Central Parking','city'=>'Sandakan','address'=>'Bandar Sandakan','rate'=>PARKING_HOURLY_CENTS / 100,'capacity'=>24,'lat'=>5.8402,'lng'=>118.1179],
 $tawauMarket + ['rate' => PARKING_HOURLY_CENTS / 100, 'capacity' => 30],
];
// Upgrade the old seed in place so existing sessions still reference the same location.
foreach (MyParkData::$records['demo_lots'] as $key => $lot) {
    if ($lot['id'] === 3 && $lot['name'] === 'Tawau Town Centre'
        && $lot['city'] === 'Tawau' && $lot['address'] === 'Jalan Dunlop'
        && ($lot['lat'] ?? null) === 4.2498 && ($lot['lng'] ?? null) === 117.8871
        && !isset($lot['deleted_at'])) {
        MyParkData::$records['demo_lots'][$key] = array_replace($lot, $tawauMarket);
        break;
    }
}
MyParkData::$records['demo_bookings'] ??= [];
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
const CUSTOMER_WALLET_REQUIRED = 'Only customers can use the wallet or make payments.';

function can_use_customer_wallet(?array $account): bool {
    return ($account['role'] ?? null) === 'user';
}

function &demo_wallet(): array {
    if (!can_use_customer_wallet(me())) throw new RuntimeException(CUSTOMER_WALLET_REQUIRED, 403);
    $userId = me()['id'];
    MyParkData::$records['demo_wallets'][$userId] ??= ['balance' => 30.00, 'transactions' => []];
    return MyParkData::$records['demo_wallets'][$userId];
}
function require_login(string $loginPath = 'pages/login.php'){
    if (!me()) { header('Location: ' . $loginPath); exit; }
    foreach (MyParkData::$records['demo_users'] as $key => $account) {
        if ($account['id'] === me()['id'] && !$account['blocked'] && $account['role'] === me()['role']) {
            $_SESSION['mypark_account_key'] = (string)$key;
            $_SESSION['user']['email'] = $account['email'];
            return;
        }
    }
    unset($_SESSION['user'], $_SESSION['mypark_account_key']);
    http_response_code(403); exit('Your account is no longer available. Please sign in again.');
}
function require_customer_wallet(): void {
    require_login();
    if (!can_use_customer_wallet(me())) {
        http_response_code(403);
        exit(te(CUSTOMER_WALLET_REQUIRED));
    }
}
const PARKING_CANCELLATION_SECONDS = 180;
const PARKING_CANCELLATION_POLICY = 'You can cancel within the first 3 minutes of parking.';
const PARKING_CANCELLATION_CLOSED = 'The 3-minute cancellation window has closed.';

function booking_cancellation_deadline(array $booking): ?int {
    $expiry = booking_expiry($booking);
    $hours = filter_var($booking['hours'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
    if ($expiry === null || $hours === false) return null;
    $startedAt = $expiry - $hours * 3600;
    return $startedAt + PARKING_CANCELLATION_SECONDS;
}

function complete_expired_bookings(): void {
    $now = time();
    $changed = false;
    foreach (MyParkData::$records['demo_bookings'] as &$booking) {
        if ($booking['status'] !== 'active') continue;
        $expiry = booking_expiry($booking);
        if ($expiry === null || $expiry > $now) continue;
        $booking['status'] = 'completed';
        $changed = true;
    }
    unset($booking);
    if ($changed) MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
}
function cancel_booking(int $id, int $userId): void {
    complete_expired_bookings();
    foreach (MyParkData::$records['demo_bookings'] as &$booking) {
        if ($booking['id'] !== $id || $booking['user_id'] !== $userId) continue;
        if ($booking['status'] !== 'active') throw new RuntimeException('Only active bookings can be cancelled.', 409);
        $deadline = booking_cancellation_deadline($booking);
        if ($deadline === null || time() >= $deadline) throw new RuntimeException(PARKING_CANCELLATION_CLOSED, 409);

        parking_add_notice($userId, [
            'type' => 'cancelled', 'location' => $booking['lot'], 'plate' => $booking['plate'],
        ]);
        $booking['status'] = 'cancelled';
        MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
        return;
    }
    throw new RuntimeException('Booking not found.', 404);
}
function require_admin(){require_login();if(!in_array(me()['role'],['admin','superadmin'],true)){http_response_code(403);exit('Forbidden');}}
function flash(string|array|null $message = null): string|array|null {
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $message;
}
function profile_nav(array $account): void {
    $photo = $account['photo'] ?? '';
    $role = match ($account['role']) {
        'user' => 'Member',
        'superadmin' => 'Super Admin',
        default => ucfirst($account['role']),
    };
    ?>
    <details class="profile-nav-menu" data-profile-nav-menu>
      <summary class="profile-nav-trigger" aria-label="<?= te('Profile') ?>">
        <?php if ($photo): ?>
          <img class="profile-nav-avatar" src="<?= e($photo) ?>" alt="" width="32" height="32">
        <?php else:
            preg_match('/[\p{L}\p{N}]/u', $account['name'], $initial);
        ?>
          <span class="profile-nav-avatar profile-nav-initial" aria-hidden="true"><?= e(strtoupper($initial[0] ?? 'P')) ?></span>
        <?php endif; ?>
      </summary>
      <div class="profile-nav-options">
        <div class="profile-nav-account">
          <strong><?= e($account['name']) ?></strong>
          <small><?= te($role) ?></small>
        </div>
        <a href="profile.php"><?= te('Profile') ?></a>
        <?php if ($account['role'] === 'user'): ?>
          <a href="oku-verification.php"><?= te('OKU Parking Verification') ?></a>
        <?php endif; ?>
        <a href="logout.php"><?= te('Logout') ?></a>
      </div>
    </details>
    <?php
}

function head(string $title): void {
    if (ajax_table_fragment() !== null) return;
    if ((me()['role'] ?? '') === 'staff') {
        require_once __DIR__ . '/staff-layout.php';
        staff_head($title);
        return;
    }
    $user = me();
    $message = flash();
    if ($user) {
        $links = ['dashboard.php' => 'Parking'];
        if ($user['role'] === 'user') $links['find_parking.php'] = 'Find parking';
        $links['receipts.php'] = 'Receipts';
        if (can_use_customer_wallet($user)) $links['wallet.php'] = 'Wallet';
        $links['compound.php'] = 'Compounds';
    } else {
        $links = ['pages/login.php' => 'Login', 'pages/register.php' => 'Register'];
    }
    if (in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) {
        unset($links['dashboard.php']);
        $links = [
            'admin/dashboard.php' => 'Dashboard',
            'admin/add-parking-location.php' => 'Add parking location',
        ] + $links;
        $links['admin/employee-attendance.php'] = 'Employee Attendance';
    }
    header('Content-Language: ' . current_language());
    ?>
    <!doctype html>
    <html lang="<?= e(current_language()) ?>">
    <head>
      <meta charset="utf-8">
      <?php if (defined('MYPARK_ADMIN_PAGE') || defined('MYPARK_AUTH_PAGE')): ?><base href="../"><?php endif; ?>
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title><?= te($title) ?> - MyPark Sabah</title>
      <link rel="icon" type="image/png" sizes="64x64" href="assets/images/favicon.png">
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
      <script src="assets/js/loading.js"></script>
      <script src="assets/js/theme.js"></script>
      <link rel="stylesheet" href="assets/css/style.css">
      <link rel="stylesheet" href="assets/css/glass.css">
      <link rel="stylesheet" href="assets/css/ajax-tables.css">
      <?php if (($user['role'] ?? '') === 'user'): ?>
        <link rel="stylesheet" href="assets/css/customer.css">
      <?php endif; ?>
    </head>
    <body>
      <header class="site-header">
        <a class="logo" href="index.php" aria-label="MyPark Sabah"></a>
        <nav class="site-nav" id="site-navigation" aria-label="<?= te('Main menu') ?>">
          <div class="nav-pages">
            <?php foreach ($links as $href => $label): ?><a href="<?= e($href) ?>"><?= te($label) ?></a><?php endforeach; ?>
          </div>
          <?php if ($user): ?>
            <div class="nav-account">
              <?php profile_nav($user); ?>
            </div>
          <?php endif; ?>
          <div class="nav-preferences">
            <?php language_switcher(); ?>
            <?php require __DIR__ . '/theme-toggle.php'; ?>
          </div>
        </nav>
        <?php if ($user): ?>
          <?php require __DIR__ . '/notification-menu.php'; ?>
        <?php endif; ?>
      </header>
      <main>
      <?php localized_flash($message ?? ''); ?>
    <?php
}

function foot(bool $showChatbot = true): void {
    if (ajax_table_fragment() !== null) {
        ob_clean();
        if (http_response_code() < 400) http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: private, no-store');
        exit(t('Table unavailable.'));
    }
    if ((me()['role'] ?? '') === 'staff') {
        require_once __DIR__ . '/staff-layout.php';
        staff_foot($showChatbot);
        return;
    }
    ?>
      </main>
      <footer>
        <section><b>MyPark Sabah</b><p><?= te('Simple parking across Sabah.') ?></p></section>
        <section><b><?= te('Contact') ?></b><p>support@mypark-sabah.demo<br>+60 88 000 000</p></section>
        <section><b><?= te('Help') ?></b><p><a href="support.php"><?= te('Customer support') ?></a><br><a href="profile.php"><?= te('My profile') ?></a></p></section>
      </footer>
      <div id="toast-stack" class="pe-toast-stack" role="region" aria-label="<?= te('Notifications') ?>"></div>
      <div id="toast-status" class="pe-toast-announcer" role="status" aria-live="polite" aria-atomic="true"></div>
      <div id="toast-alert" class="pe-toast-announcer" role="alert" aria-live="assertive" aria-atomic="true"></div>
      <?php locale_client_scripts(); ?>
      <script src="assets/js/ajax-tables.js"></script>
      <script src="assets/js/toasts.js"></script>
      <script src="assets/js/app.js"></script>
      <?php if (me()): ?><script src="assets/js/notifications.js"></script><?php endif; ?>
      <?php if ($showChatbot): ?>
        <script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>
        <script src="https://files.bpcontent.cloud/2026/11/13/14/20261113143638-NZ5LEIAP.js" defer></script>
      <?php endif; ?>
    </body>
    </html>
    <?php
}
