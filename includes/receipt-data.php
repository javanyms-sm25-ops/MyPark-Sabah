<?php

const RECEIPT_TYPES = [
    'parking' => 'Parking',
    'compound' => 'Compounds',
    'topup' => 'Wallet top-ups',
];

function receipt_window(): array {
    $today = new DateTimeImmutable('today');
    return [$today->modify('-29 days'), $today];
}

function receipt_date(string $value, string $format = 'Y-m-d'): ?DateTimeImmutable {
    if (str_contains($value, "\0")) return null;
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
    return $date && $date->format($format) === $value ? $date : null;
}

function receipt_time(int $timestamp): string {
    return date('j M Y, g:i a', $timestamp);
}

function receipt_money(int $cents): string {
    return 'RM ' . number_format($cents / 100, 2);
}

function receipt_amount_label(array $receipt): string {
    return match ($receipt['type']) {
        'parking' => 'Parking fee',
        'compound' => 'Amount paid',
        'topup' => 'Amount added',
    };
}

function receipt_title(array $receipt): string {
    return match ($receipt['type']) {
        'parking' => 'Parking booking',
        'compound' => 'Compound payment',
        'topup' => 'Wallet top-up',
    };
}

function receipt_add(array &$source, int $userId, string $type, int $amountCents, array $details, int $oldest): void {
    if (isset($source['receipt_id'])) return;
    $created = receipt_date($source['created_at'], 'Y-m-d H:i');
    if (!$created || $created->getTimestamp() < $oldest || $created->getTimestamp() > time()) return;

    $id = strtoupper(bin2hex(random_bytes(8)));
    $_SESSION['demo_receipts'][$userId][$id] = [
        'id' => $id,
        'type' => $type,
        'at' => $created->getTimestamp(),
        'amount_cents' => $amountCents,
        'details' => $details,
    ];
    $source['receipt_id'] = $id;
}

function receipt_sync(): void {
    [$firstDay] = receipt_window();
    $oldest = $firstDay->getTimestamp();
    $_SESSION['demo_receipts'] ??= [];

    foreach ($_SESSION['demo_receipts'] as $userId => $receipts) {
        $recent = array_filter($receipts, static fn($receipt) => $receipt['at'] >= $oldest);
        if ($recent) {
            $_SESSION['demo_receipts'][$userId] = $recent;
        } else {
            unset($_SESSION['demo_receipts'][$userId]);
        }
    }

    foreach ($_SESSION['demo_bookings'] as &$booking) {
        $start = receipt_date($booking['created_at'], 'Y-m-d H:i');
        if (!$start || $start->getTimestamp() < $oldest || isset($booking['receipt_id'])) continue;

        $details = [
            'Booking number' => '#' . $booking['id'],
            'Vehicle plate' => $booking['plate'],
            'Location' => $booking['lot'] . (!empty($booking['city']) ? ', ' . $booking['city'] : ''),
            'Parking starts' => receipt_time($start->getTimestamp()),
            'Parking ends' => receipt_time(booking_expiry($booking) ?? $start->getTimestamp() + $booking['hours'] * 3600),
            'Duration' => $booking['hours'] . ($booking['hours'] === 1 ? ' hour' : ' hours'),
        ];
        receipt_add($booking, $booking['user_id'], 'parking', (int) round($booking['fee'] * 100), $details, $oldest);
    }
    unset($booking);

    $_SESSION['demo_wallets'] ??= [];
    foreach ($_SESSION['demo_wallets'] as $userId => &$wallet) {
        foreach ($wallet['transactions'] as &$transaction) {
            if (($transaction['status'] ?? '') !== 'paid') continue;
            $type = ($transaction['type'] ?? 'topup') === 'compound' ? 'compound' : 'topup';
            $details = $type === 'compound' ? [
                'Compound number' => $transaction['compound'],
                'Payment reference' => $transaction['reference'],
                'Payment method' => 'MyPark demo wallet',
            ] : [];
            if ($type === 'compound' && !empty($transaction['plate'])) {
                $details['Vehicle plate'] = $transaction['plate'];
            }
            receipt_add($transaction, $userId, $type, (int) round($transaction['amount'] * 100), $details, $oldest);
        }
        unset($transaction);
    }
    unset($wallet);
}

function receipt_text(array $receipt): string {
    $lines = [
        'MyPark Sabah — ' . receipt_title($receipt),
        'Receipt number: ' . $receipt['id'],
        'Date: ' . receipt_time($receipt['at']) . ' (Malaysia time)',
    ];
    foreach ($receipt['details'] as $label => $value) $lines[] = $label . ': ' . $value;
    $lines[] = receipt_amount_label($receipt) . ': ' . receipt_money($receipt['amount_cents']);
    $lines[] = $receipt['type'] === 'parking' ? 'Demo booking. No payment was collected.' : 'Demo transaction.';
    return implode("\r\n", $lines);
}
