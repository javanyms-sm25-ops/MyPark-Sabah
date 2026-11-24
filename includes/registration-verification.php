<?php

require_once __DIR__ . '/email-delivery.php';

const REGISTRATION_LINK_LIFETIME = 600;

function registration_email_exists(string $email): bool {
    return in_array($email, array_column($_SESSION['demo_users'], 'email'), true);
}

function registration_link_is_active(?array $pending): bool {
    return isset($pending['token_hash']) && $pending['expires_at'] > time();
}

function registration_resend_wait(array $pending): int {
    if (!registration_link_is_active($pending)) return 0;
    $sentAt = $pending['expires_at'] - REGISTRATION_LINK_LIFETIME;
    return max(0, $sentAt + 60 - time());
}

function registration_issue_link(array $account, callable $deliver): void {
    reserve_email_delivery('registration_delivery');
    $token = bin2hex(random_bytes(32));
    $deliver($account['email'], $token);
    $_SESSION['registration_pending'] = [
        'account' => $account,
        'token_hash' => hash('sha256', $token),
        'expires_at' => time() + REGISTRATION_LINK_LIFETIME,
    ];
}

function registration_complete(string $token): void {
    $pending = $_SESSION['registration_pending'] ?? null;
    if (!registration_link_is_active($pending)
        || !preg_match('/\A[0-9a-f]{64}\z/', $token)
        || !hash_equals($pending['token_hash'], hash('sha256', $token))) {
        throw new RuntimeException('This verification link is invalid or has expired. Request a new one.', 422);
    }

    $account = $pending['account'];
    if (registration_email_exists($account['email'])) {
        unset($_SESSION['registration_pending']);
        throw new RuntimeException('Email already registered.', 409);
    }
    $_SESSION['demo_users'][] = [
        'id' => max(array_column($_SESSION['demo_users'], 'id') ?: [0]) + 1,
        'name' => $account['name'],
        'email' => $account['email'],
        'password' => $account['password'],
        'role' => 'user',
        'blocked' => false,
        'email_verified_at' => date(DATE_ATOM),
    ];
    unset($_SESSION['registration_pending']);
}
