<?php

function start_demo_session(bool $trackActivity = true): void {
    $now = time();
    $lifetime = 31 * 86400;
    $legacyName = session_name();
    // Keep using the project root so previously saved sessions still load.
    $suffix = substr(hash('sha256', dirname(__DIR__)), 0, 12);
    $name = 'MyPark' . $suffix;
    $savePathParts = explode(';', session_save_path());
    $basePath = end($savePathParts) ?: sys_get_temp_dir();
    $savePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'mypark-' . $suffix;

    if (!is_dir($savePath) && !@mkdir($savePath, 0700) && !is_dir($savePath)) {
        error_log('MyPark could not create its private session directory.');
        http_response_code(503);
        exit('MyPark could not open your saved records. Please try again later.');
    }

    $legacy = [];
    $legacyId = $_COOKIE[$legacyName] ?? '';
    if (!isset($_COOKIE[$name]) && is_string($legacyId) && preg_match('/\A[a-zA-Z0-9,-]{1,128}\z/', $legacyId)) {
        session_id($legacyId);
        if (session_start(['read_and_close' => true, 'use_cookies' => 0, 'use_strict_mode' => 1, 'gc_probability' => 0])
            && session_id() === $legacyId && isset($_SESSION['demo_users'], $_SESSION['demo_lots'])) {
            $legacy = $_SESSION;
            $ownerId = $legacy['user']['id'] ?? null;
            if ($ownerId !== null && isset($legacy['demo_wallet_balance'])) {
                $legacy['demo_wallets'][$ownerId] ??= [
                    'balance' => $legacy['demo_wallet_balance'],
                    'transactions' => $legacy['demo_wallet_transactions'] ?? [],
                ];
            }
            unset($legacy['user'], $legacy['mypark_account_key'], $legacy['csrf'], $legacy['demo_wallet_balance'], $legacy['demo_wallet_transactions']);
        }
        session_id('');
    }

    session_name($name);
    session_save_path($savePath);
    $cookie = [
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!session_start([
        'use_cookies' => 1,
        'use_only_cookies' => 1,
        'use_strict_mode' => 1,
        'cookie_lifetime' => $lifetime,
        'cookie_path' => $cookie['path'],
        'cookie_domain' => '',
        'cookie_secure' => $cookie['secure'],
        'cookie_httponly' => $cookie['httponly'],
        'cookie_samesite' => $cookie['samesite'],
        'gc_maxlifetime' => $lifetime,
        'gc_probability' => 1,
    ])) {
        http_response_code(503);
        exit('MyPark could not open your saved records. Please try again later.');
    }

    if ($legacy && !isset($_SESSION['demo_users'])) $_SESSION = $legacy;

    if (($now - ($_SESSION['demo_cookie_refreshed_at'] ?? 0)) >= 86400) {
        if (isset($_COOKIE[$name])) setcookie($name, session_id(), ['expires' => $now + $lifetime] + $cookie);
        $_SESSION['demo_cookie_refreshed_at'] = $now;
    }

    // Keep the browser's records available while expiring idle sign-ins separately.
    if (isset($_SESSION['user'])) {
        if ($now - ($_SESSION['demo_last_active'] ?? $now) >= 1440) {
            unset($_SESSION['user'], $_SESSION['mypark_account_key'], $_SESSION['csrf'], $_SESSION['demo_last_active']);
        } elseif ($trackActivity) {
            $_SESSION['demo_last_active'] = $now;
        }
    }
}
