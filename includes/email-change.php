<?php

require_once __DIR__ . '/registration-verification.php';

const EMAIL_CHANGE_LIFETIME = 600;
const EMAIL_CHANGE_INVALID_LINK = 'This verification link is invalid or has expired. Request a new one.';

function email_change_pending(array $account): ?array {
    $pending = $_SESSION['email_change_pending'] ?? null;
    if ($pending === null || $pending['expires_at'] <= time()
        || $pending['account_id'] !== $account['id'] || $pending['current_email'] !== $account['email']
        || !hash_equals($pending['password_fingerprint'], hash('sha256', $account['password']))) {
        unset($_SESSION['email_change_pending']);
        return null;
    }
    return $pending;
}

function email_change_check_password(array $account, string $password): void {
    $id = $account['id'];
    $attempts = $_SESSION['email_change_attempts'][$id] ?? ['count' => 0, 'until' => time() + 600];
    if ($attempts['until'] <= time()) $attempts = ['count' => 0, 'until' => time() + 600];
    if ($attempts['count'] >= 5) throw new RuntimeException('Too many attempts. Try again later.', 429);
    if (!password_verify($password, $account['password'])) {
        $attempts['count']++;
        $_SESSION['email_change_attempts'][$id] = $attempts;
        throw new RuntimeException('The current password is incorrect.', 422);
    }
    unset($_SESSION['email_change_attempts'][$id]);
}

function email_change_issue_link(array $account, string $email, callable $deliver): void {
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter an email address like name@example.com.', 422);
    }
    if ($email === strtolower($account['email'])) {
        throw new RuntimeException('Enter an address different from your current email.', 422);
    }
    if (registration_email_exists($email)) throw new RuntimeException('Email already registered.', 409);

    reserve_email_delivery('email_change_delivery', 'Wait one minute before requesting another verification link.');
    $token = bin2hex(random_bytes(32));
    $deliver($email, $token);
    $_SESSION['email_change_pending'] = [
        'account_id' => $account['id'],
        'current_email' => $account['email'],
        'new_email' => $email,
        'password_fingerprint' => hash('sha256', $account['password']),
        'token_hash' => hash('sha256', $token),
        'expires_at' => time() + EMAIL_CHANGE_LIFETIME,
    ];
}

function email_change_verify_link(array $account, string $token): void {
    $pending = email_change_pending($account);
    if (!isset($pending['token_hash']) || !preg_match('/\A[0-9a-f]{64}\z/', $token)
        || !hash_equals($pending['token_hash'], hash('sha256', $token))) {
        throw new RuntimeException(EMAIL_CHANGE_INVALID_LINK, 422);
    }
    unset($_SESSION['email_change_pending']['token_hash']);
    $_SESSION['email_change_pending']['verified'] = true;
}

function email_change_complete(array &$account): void {
    $pending = email_change_pending($account);
    if (empty($pending['verified'])) throw new RuntimeException(EMAIL_CHANGE_INVALID_LINK, 422);

    $email = $pending['new_email'];
    if (registration_email_exists($email)) {
        unset($_SESSION['email_change_pending']);
        throw new RuntimeException('Email already registered.', 409);
    }
    $oldEmail = $account['email'];
    $account['email'] = $_SESSION['user']['email'] = $email;
    $account['email_verified_at'] = date(DATE_ATOM);
    MyParkData::$records['ops_revision'] = (MyParkData::$records['ops_revision'] ?? 0) + 1;
    unset($_SESSION['email_change_pending'], $_SESSION['password_reset_pending'], $_SESSION['failures'][$oldEmail]);
}
