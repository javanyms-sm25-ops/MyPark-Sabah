<?php
require_once __DIR__ . '/staff-data.php';

function staff_head(string $title): void {
    try { $actor = staff_actor(); }
    catch (RuntimeException $error) { http_response_code($error->getCode()); exit(e($error->getMessage())); }
    $profile = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'profile.php';
    $receipts = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'receipts.php';
    $page = $profile ? 'profile' : ($receipts ? 'receipts' : ($_GET['page'] ?? 'dashboard'));
    $links = ['dashboard' => ['Dashboard', 'staff.php'], 'fines' => ['Fine Management', 'staff.php?page=fines'],
        'vehicles' => ['Vehicle Lookup', 'staff.php?page=vehicles'], 'maintenance' => ['Parking Maintenance', 'staff.php?page=maintenance'],
        'receipts' => ['Receipts', 'receipts.php'], 'profile' => ['My Profile', 'profile.php']];
    $message = flash();
    header('Content-Language: ' . current_language());
    ?>
    <!doctype html><html lang="<?= e(current_language()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (defined('MYPARK_ADMIN_PAGE') || defined('MYPARK_AUTH_PAGE')): ?><base href="../"><?php endif; ?>
    <title><?= te($title) ?> · MyPark Sabah</title>
    <link rel="icon" type="image/png" sizes="64x64" href="assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/staff.css"></head>
    <body class="staff-portal<?= $profile ? ' staff-profile-page' : '' ?>">
    <a class="staff-skip" href="#staff-main"><?= te('Skip to content') ?></a>
    <div class="staff-backdrop" id="staff-backdrop" hidden></div>
    <aside class="staff-sidebar" id="staff-sidebar" aria-label="<?= te('Staff navigation') ?>">
      <button type="button" class="staff-sidebar-close staff-button staff-secondary"><?= te('Close menu') ?></button>
      <a class="staff-brand" href="staff.php"><span class="staff-monogram" aria-hidden="true">MP</span><span>MyPark Sabah<small><?= te('Staff workspace') ?></small></span></a>
      <p class="staff-nav-label"><?= te('WORKSPACE') ?></p>
      <nav aria-label="<?= te('Staff pages') ?>">
        <?php foreach ($links as $key => [$label, $href]): ?><a href="<?= e($href) ?>" <?= $page === $key ? 'aria-current="page"' : '' ?>><?= te($label) ?></a><?php endforeach; ?>
      </nav>
      <div class="staff-sidebar-bottom"><strong><?= e(me()['name']) ?></strong><span><?= te('Staff') ?> · Sabah</span><?php language_switcher(); ?></div>
    </aside>
    <div class="staff-workspace">
      <header class="staff-topbar">
        <button type="button" class="staff-menu-toggle" aria-controls="staff-sidebar" aria-expanded="false"><?= te('Menu') ?></button>
        <span><?= te($links[$page][0] ?? $title) ?></span>
        <span class="staff-context"><?= te('Private parking · Staff portal') ?></span>
        <?php profile_nav(me()); ?>
      </header>
      <main id="staff-main" class="staff-main" tabindex="-1">
        <?php localized_flash($message ?? ''); ?>
    <?php
}

function staff_foot(bool $showChatbot = true): void {
    ?>
      </main>
      <p class="staff-storage-note"><?= te('Private parking prototype. Compounds and digital receipts are saved in this browser across sign-outs.') ?></p>
    </div>
    <div id="toast-stack" class="pe-toast-stack" role="region" aria-label="<?= te('Notifications') ?>"></div>
    <div id="toast-status" class="pe-toast-announcer" role="status" aria-live="polite" aria-atomic="true"></div>
    <div id="toast-alert" class="pe-toast-announcer" role="alert" aria-live="assertive" aria-atomic="true"></div>
    <?php locale_client_scripts(); ?>
    <script src="assets/js/toasts.js"></script><script src="assets/js/app.js"></script>
    <script src="assets/js/operations-table.js"></script><script src="assets/js/staff.js"></script>
    <?php if ($showChatbot): ?>
      <script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>
      <script src="https://files.bpcontent.cloud/2026/11/13/14/20261113143638-NZ5LEIAP.js" defer></script>
    <?php endif; ?>
    </body></html>
    <?php
}
