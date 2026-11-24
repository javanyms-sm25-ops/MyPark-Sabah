<?php

require_once __DIR__ . '/email-delivery.php';

const EMAIL_OTP_LIFETIME = 600;
const EMAIL_OTP_ATTEMPTS = 5;

function issue_email_otp(string $deliveryKey, callable $deliver): array {
    reserve_email_delivery($deliveryKey, 'Wait one minute before requesting another code.');
    $code = sprintf('%06d', random_int(0, 999999));
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $deliver($code);
    return ['code_hash' => $hash, 'expires_at' => time() + EMAIL_OTP_LIFETIME, 'attempts' => 0];
}

function verify_email_otp(?array &$challenge, string $code): void {
    if ($challenge === null || $challenge['expires_at'] <= time()) {
        throw new RuntimeException('Your verification code has expired. Request a new code.', 422);
    }
    if ($challenge['attempts'] >= EMAIL_OTP_ATTEMPTS) {
        throw new RuntimeException('Too many incorrect codes. Request a new code.', 429);
    }
    if (!preg_match('/\A[0-9]{6}\z/', $code)) {
        throw new RuntimeException('Enter the six-digit verification code.', 422);
    }
    if (!password_verify($code, $challenge['code_hash'])) {
        $challenge['attempts']++;
        throw new RuntimeException('Incorrect verification code.', 422);
    }
}
