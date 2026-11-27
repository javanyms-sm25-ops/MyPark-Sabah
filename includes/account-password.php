<?php

function validate_new_account_password(string $password, string $confirmation): void {
    if (preg_match_all('/./us', $password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters.', 422);
    }
    if (strlen($password) > 72) {
        throw new RuntimeException('Password must be no more than 72 bytes (some characters use more than one byte).', 422);
    }
    if ($password !== $confirmation) {
        throw new RuntimeException('Passwords do not match.', 422);
    }
}

function change_account_password(string $current, string $password, string $confirmation): void {
    // Login validation supplies the current account key for this workspace.
    $key = $_SESSION['mypark_account_key'];
    $account = MyParkData::$records['demo_users'][$key];
    $now = time();
    $attempts = $_SESSION['password_change_attempts'][$account['id']] ?? null;
    if ($attempts === null || $attempts['until'] <= $now) {
        $attempts = ['count' => 0, 'until' => $now + 600];
    }
    if ($attempts['count'] >= 5) throw new RuntimeException('Too many attempts. Try again later.', 429);
    if (!password_verify($current, $account['password'])) {
        $attempts['count']++;
        $_SESSION['password_change_attempts'][$account['id']] = $attempts;
        throw new RuntimeException('The current password is incorrect.', 422);
    }
    unset($_SESSION['password_change_attempts'][$account['id']]);
    validate_new_account_password($password, $confirmation);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    MyParkData::$records['demo_users'][$key]['password'] = $hash;
    // Only this browser receives the new fingerprint. Other sessions expire on their next request.
    $_SESSION['demo_login']['password_fingerprint'] = hash('sha256', $hash);
}
