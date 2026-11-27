<?php

const MYPARK_REMEMBER_LOGIN_SECONDS = 30 * 86400;
const MYPARK_LOGIN_IDLE_SECONDS = 24 * 60;

function demo_cookie_options(): array {
    return [
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function start_demo_login(string $passwordHash, bool $remember): bool {
    if (!session_regenerate_id(true)) return false;

    $now = time();
    $expires = $remember ? $now + MYPARK_REMEMBER_LOGIN_SECONDS : 0;
    $token = bin2hex(random_bytes(32));
    if (!setcookie(session_name() . 'Auth', $token, ['expires' => $expires] + demo_cookie_options())) {
        return false;
    }

    $_SESSION['demo_login'] = [
        'token_hash' => hash('sha256', $token),
        'password_fingerprint' => hash('sha256', $passwordHash),
        'expires' => $expires,
    ];
    $_SESSION['demo_last_active'] = $now;
    unset($_SESSION['csrf']);
    return true;
}

function clear_demo_login(): void {
    unset($_SESSION['user'], $_SESSION['mypark_account_key'], $_SESSION['csrf'], $_SESSION['demo_login'], $_SESSION['demo_last_active']);
    setcookie(session_name() . 'Auth', '', ['expires' => 1] + demo_cookie_options());
}

function demo_login_matches_password(array $session, string $passwordHash): bool {
    return hash_equals($session['demo_login']['password_fingerprint'] ?? '', hash('sha256', $passwordHash));
}

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
    $cookie = demo_cookie_options();
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

    // The persistent workspace cookie still opens the workspace after sign-out.
    // A separate credential controls whether the user is signed in.
    if (isset($_SESSION['user'])) {
        $login = $_SESSION['demo_login'] ?? null;
        $token = $_COOKIE[$name . 'Auth'] ?? null;
        $expires = $login['expires'] ?? 0;
        $lastActive = $_SESSION['demo_last_active'] ?? 0;
        $expired = $expires > 0 ? $now >= $expires : $now - $lastActive >= MYPARK_LOGIN_IDLE_SECONDS;
        if ($expired || !is_string($token)
            || !hash_equals($login['token_hash'] ?? '', hash('sha256', $token))) {
            clear_demo_login();
        } elseif ($trackActivity && $expires === 0) {
            $_SESSION['demo_last_active'] = $now;
        }
    }
}
