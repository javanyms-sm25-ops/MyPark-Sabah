<?php

require_once __DIR__ . '/email-otp.php';

function password_reset_account_index(string $email): ?int {
    foreach ($_SESSION['demo_users'] as $index => $account) {
        if ($account['email'] === $email) return $index;
    }
    return null;
}

function password_reset_issue_code(string $email, callable $deliver): void {
    $index = password_reset_account_index($email);
    $account = $index === null ? null : $_SESSION['demo_users'][$index];
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
    if (!$delivered && $previous && $previous['email'] === $email && $previous['account_id'] !== null
        && $previous['expires_at'] > time() && $previous['attempts'] < EMAIL_OTP_ATTEMPTS) {
        return;
    }
    $_SESSION['password_reset_pending'] = [
        'email' => $email,
        'account_id' => $delivered ? $account['id'] : null,
        'password_fingerprint' => $delivered ? hash('sha256', $account['password']) : null,
    ] + $challenge;
}

function password_reset_complete(string $code, string $password, string $confirmation): int {
    if (preg_match_all('/./us', $password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters.', 422);
    }
    if (strlen($password) > 72) {
        throw new RuntimeException('Password must be no more than 72 bytes (some characters use more than one byte).', 422);
    }
    if ($password !== $confirmation) {
        throw new RuntimeException('Passwords do not match.', 422);
    }

    $pending =& $_SESSION['password_reset_pending'];
    verify_email_otp($pending, $code);
    $index = password_reset_account_index($pending['email']);
    $account = $index === null ? null : $_SESSION['demo_users'][$index];
    if ($account === null || $account['id'] !== $pending['account_id']
        || !hash_equals(hash('sha256', $account['password']), $pending['password_fingerprint'])) {
        unset($_SESSION['password_reset_pending']);
        throw new RuntimeException('Your verification code has expired. Request a new code.', 422);
    }

    $_SESSION['demo_users'][$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
    unset($_SESSION['password_reset_pending'], $_SESSION['failures'][$account['email']]);
    return $account['id'];
}
