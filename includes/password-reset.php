<?php

require_once __DIR__ . '/email-otp.php';
require_once __DIR__ . '/account-password.php';

function password_reset_account_index(string $email): ?int {
    foreach (MyParkData::$records['demo_users'] as $index => $account) {
        if ($account['email'] === $email) return $index;
    }
    return null;
}

function password_reset_issue_code(string $email, callable $deliver): void {
    $index = password_reset_account_index($email);
    $account = $index === null ? null : MyParkData::$records['demo_users'][$index];
    $delivered = false;

    // Use the same browser response for unknown addresses and mail delivery errors.
    $challenge = issue_email_otp('password_reset_delivery', static function (string $code) use ($email, $account, $deliver, &$delivered): void {
        if ($account === null) return;
        try {
            $deliver($email, $code);
            $delivered = true;
        } catch (RuntimeException $error) {
            error_log('MyPark password reset code could not be sent.');
        }
    });

    $previous = $_SESSION['password_reset_pending'] ?? null;
    if (!$delivered && isset($previous['code_hash']) && $previous['email'] === $email && $previous['account_id'] !== null
        && $previous['expires_at'] > time() && $previous['attempts'] < EMAIL_OTP_ATTEMPTS) {
        return;
    }
    $_SESSION['password_reset_pending'] = [
        'email' => $email,
        'account_id' => $delivered ? $account['id'] : null,
        'password_fingerprint' => $delivered ? hash('sha256', $account['password']) : null,
    ] + $challenge;
}

function password_reset_is_verified(?array $pending): bool {
    return isset($pending['verified_at']) && $pending['expires_at'] > time();
}

function password_reset_target_index(array $pending): int {
    $index = password_reset_account_index($pending['email']);
    $account = $index === null ? null : MyParkData::$records['demo_users'][$index];
    if ($account === null || $account['id'] !== $pending['account_id']
        || !hash_equals(hash('sha256', $account['password']), $pending['password_fingerprint'])) {
        unset($_SESSION['password_reset_pending']);
        throw new RuntimeException('Your verification code has expired. Request a new code.', 422);
    }
    return $index;
}

function password_reset_verify_code(string $code): void {
    $pending =& $_SESSION['password_reset_pending'];
    if (!isset($pending['code_hash'])) {
        throw new RuntimeException('Your verification code has expired. Request a new code.', 422);
    }
    verify_email_otp($pending, $code);
    password_reset_target_index($pending);
    // Keep the original expiry and discard the code once it has been verified.
    $pending['verified_at'] = time();
    unset($pending['code_hash'], $pending['attempts']);
}

function password_reset_complete(string $password, string $confirmation): int {
    $pending = $_SESSION['password_reset_pending'] ?? null;
    if (!password_reset_is_verified($pending)) {
        throw new RuntimeException('Verify your email code before changing your password.', 422);
    }
    $index = password_reset_target_index($pending);

    validate_new_account_password($password, $confirmation);

    MyParkData::$records['demo_users'][$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
    unset($_SESSION['password_reset_pending'], $_SESSION['failures'][$pending['email']]);
    return MyParkData::$records['demo_users'][$index]['id'];
}
