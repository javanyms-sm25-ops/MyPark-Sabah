<?php

require_once __DIR__ . '/email-delivery.php';

const REGISTRATION_LINK_LIFETIME = 600;
const REGISTRATION_LINK_INVALID = 'This verification link is invalid or has expired. Request a new one.';

function registration_email_exists(string $email): bool {
    return in_array($email, array_column(MyParkData::$records['demo_users'], 'email'), true);
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
    $tokenHash = hash('sha256', $token);
    $requests =& MyParkData::$records['pending_registrations'];
    $previousHash = $_SESSION['registration_pending']['token_hash'] ?? '';
    $requests = array_filter($requests ?? [], 'registration_link_is_active');
    unset($requests[$previousHash]);
    $pending = [
        'account' => $account,
        'token_hash' => $tokenHash,
        'expires_at' => time() + REGISTRATION_LINK_LIFETIME,
    ];
    $requests[$tokenHash] = $pending;
    $_SESSION['registration_pending'] = $pending + ['stored' => true];
}

function registration_confirmation_request(string $tokenHash): array {
    $pending = MyParkData::$records['pending_registrations'][$tokenHash] ?? null;
    $local = $_SESSION['registration_pending'] ?? null;
    if ($pending === null && empty($local['stored']) && registration_confirmation_is_local($tokenHash)) {
        // Links issued before pending requests were stored in the database still work in their original browser.
        $pending = $local;
    }
    if (!registration_link_is_active($pending)) throw new RuntimeException(REGISTRATION_LINK_INVALID, 422);
    return $pending;
}

function registration_confirmation_is_local(string $tokenHash): bool {
    $localHash = $_SESSION['registration_pending']['token_hash'] ?? '';
    return $localHash !== '' && hash_equals($localHash, $tokenHash);
}

function registration_complete(string $tokenHash, string $password): void {
    $pending = registration_confirmation_request($tokenHash);
    $local = registration_confirmation_is_local($tokenHash);
    $account = $pending['account'];
    if (registration_email_exists($account['email'])) {
        unset(MyParkData::$records['pending_registrations'][$tokenHash]);
        if ($local) unset($_SESSION['registration_pending']);
        throw new RuntimeException('Email already registered.', 409);
    }
    if (!$local) {
        // The email proves mailbox access. The password also ties this browser to the person who signed up.
        $attempts = $pending['password_attempts'] ?? 0;
        if ($attempts >= 5) throw new RuntimeException('Too many attempts. Try again later.', 429);
        if ($password === '' || strlen($password) > 72 || !password_verify($password, $account['password'])) {
            MyParkData::$records['pending_registrations'][$tokenHash]['password_attempts'] = $attempts + 1;
            throw new RuntimeException('Enter the password you chose when you registered.', 422);
        }
    }
    MyParkData::$records['demo_users'][] = [
        'id' => max(array_column(MyParkData::$records['demo_users'], 'id') ?: [0]) + 1,
        'name' => $account['name'],
        'email' => $account['email'],
        'password' => $account['password'],
        'role' => 'user',
        'blocked' => false,
        'email_verified_at' => date(DATE_ATOM),
    ];
    unset(MyParkData::$records['pending_registrations'][$tokenHash], $_SESSION['registration_confirmation']);
    if ($local) unset($_SESSION['registration_pending']);
}
