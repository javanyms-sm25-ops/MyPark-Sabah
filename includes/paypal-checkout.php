<?php

const PAYPAL_TOPUP_MIN_CENTS = 100;

function paypal_config(): array {
    $path = dirname(__DIR__) . '/config/paypal.local.php';
    $config = is_file($path) ? require $path : [];
    return is_array($config) ? $config : [];
}

function paypal_amount_cents(mixed $raw): ?int {
    if (!is_string($raw) || !preg_match('/\A(?:[1-9]\d{0,2}|[1-4]\d{3}|500)(?:\.\d{1,2})?\z/', $raw)) return null;
    $parts = explode('.', $raw, 2);
    $cents = ((int)$parts[0] * 100) + (int)str_pad($parts[1] ?? '', 2, '0');
    return $cents >= PAYPAL_TOPUP_MIN_CENTS && $cents <= 50000 ? $cents : null;
}

function paypal_request(string $method, string $path, array|stdClass|null $body = null, ?string $accessToken = null, ?string $requestId = null): array {
    $config = paypal_config();
    if (empty($config['client_id']) || empty($config['client_secret']) || !extension_loaded('curl')) {
        throw new RuntimeException('PayPal Sandbox is unavailable. Please try again later.', 503);
    }
    $headers = ['Accept: application/json'];
    if ($accessToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    if ($requestId !== null) $headers[] = 'PayPal-Request-Id: ' . $requestId;
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    $handle = curl_init('https://api-m.sandbox.paypal.com' . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($accessToken === null) {
        curl_setopt($handle, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
        curl_setopt($handle, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
    } elseif ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $response = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $data = is_string($response) ? json_decode($response, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('PayPal could not complete the request. Please try again.', 502);
    }
    return $data;
}

function paypal_access_token(): string {
    $data = paypal_request('POST', '/v1/oauth2/token');
    if (!is_string($data['access_token'] ?? null)) throw new RuntimeException('PayPal authentication failed.', 502);
    return $data['access_token'];
}

function paypal_paid_cents(array $order): ?int {
    if (($order['status'] ?? '') !== 'COMPLETED') return null;
    $units = $order['purchase_units'] ?? [];
    if (!is_array($units) || count($units) !== 1) return null;
    $captures = $units[0]['payments']['captures'] ?? [];
    if (!is_array($captures) || count($captures) !== 1 || ($captures[0]['status'] ?? '') !== 'COMPLETED') return null;
    $amount = $captures[0]['amount'] ?? [];
    if (($amount['currency_code'] ?? '') !== 'MYR') return null;
    return paypal_amount_cents($amount['value'] ?? null);
}

function paypal_topup_verified(array $order, array $checkout): bool {
    return ($order['id'] ?? '') === $checkout['order_id']
        && ($order['purchase_units'][0]['custom_id'] ?? '') === $checkout['nonce']
        && paypal_paid_cents($order) === $checkout['cents'];
}
