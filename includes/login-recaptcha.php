<?php

function login_recaptcha_accepts(array $result, array $hosts, float $threshold): bool {
    $hostname = $result['hostname'] ?? null;
    $score = $result['score'] ?? null;
    return ($result['success'] ?? null) === true
        && ($result['action'] ?? null) === 'login'
        && is_string($hostname) && in_array(strtolower($hostname), $hosts, true)
        && (is_float($score) || is_int($score))
        && $score >= $threshold && $score <= 1;
}

function login_recaptcha_error(array $config, string $token): string {
    $unavailable = 'Security verification is temporarily unavailable. Please try again later.';
    $retry = 'Security verification did not pass. Please try signing in again.';
    $siteKey = trim((string)($config['site_key'] ?? ''));
    $secret = trim((string)($config['secret_key'] ?? ''));
    $hosts = array_filter(array_map(
        static fn(string $host): string => strtolower(trim($host)),
        $config['allowed_hostnames'] ?? []
    ));
    $threshold = filter_var($config['min_score'] ?? 0.5, FILTER_VALIDATE_FLOAT);
    if ($siteKey === '' || $secret === '' || !$hosts || $threshold === false || $threshold <= 0 || $threshold > 1 || !function_exists('curl_init')) {
        return $unavailable;
    }
    if (trim($token) === '' || strlen($token) > 8192) return $retry;

    $request = curl_init('https://www.google.com/recaptcha/api/siteverify');
    if ($request === false) return $unavailable;
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($request);
    if (!is_string($body) || curl_getinfo($request, CURLINFO_RESPONSE_CODE) !== 200) return $unavailable;

    $result = json_decode($body, true);
    if (!is_array($result)) return $unavailable;
    // Google rejects expired or previously verified tokens as timeout-or-duplicate.
    return login_recaptcha_accepts($result, $hosts, $threshold) ? '' : $retry;
}
