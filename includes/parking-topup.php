<?php

function parking_wallet_shortfall(float $fee, float $balance): int {
    return max(0, (int)round($fee * 100) - (int)round($balance * 100));
}

function parking_topup_context(int $userId, mixed $reference): ?array {
    $pending = $_SESSION['parking_reviews'][$userId] ?? null;
    if (!is_string($reference) || !preg_match('/\A[a-f0-9]{32}\z/', $reference)
        || !isset($pending['draft'], $pending['review'], $pending['topup'])
        || !hash_equals($pending['topup'], $reference)) {
        return null;
    }
    return $pending;
}

function parking_topup_wallet_url(string $reference): string {
    return 'wallet.php?parking=' . $reference . '#bookingTopup';
}

function parking_topup_review_url(string $reference): string {
    return 'parking-details.php?step=resume&parking=' . $reference . '#parkingReview';
}
