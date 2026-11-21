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
        'parking' => isset($receipt['details']['Payment method']) ? 'Amount paid' : 'Parking fee',
        'compound' => 'Amount paid',
        'topup' => 'Amount added',
    };
}

function receipt_payment_note(array $receipt): string {
    $paid = isset($receipt['details']['Payment method']);
    return match ($receipt['type']) {
        'parking' => $paid ? 'Paid with MyPark demo wallet.' : 'Demo booking. No payment was collected.',
        'topup' => $paid ? 'PayPal Sandbox transaction.' : 'Demo transaction.',
        default => 'Demo transaction.',
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

    do {
        $id = strtoupper(bin2hex(random_bytes(8)));
    } while (isset($_SESSION['demo_receipts'][$userId][$id]));
    $_SESSION['demo_receipts'][$userId][$id] = [
        'id' => $id,
        'type' => $type,
        'at' => $created->getTimestamp(),
        'amount_cents' => $amountCents,
        'details' => $details,
    ];
    $source['receipt_id'] = $id;
}

function receipt_assign_numbers(): void {
    $used = [];
    foreach ($_SESSION['demo_receipts'] as $receipts) {
        foreach (array_column($receipts, 'number') as $number) $used[$number] = true;
    }

    foreach ($_SESSION['demo_receipts'] as &$receipts) {
        foreach ($receipts as &$receipt) {
            if (isset($receipt['number'])) continue;

            $plate = strtoupper(preg_replace('/[^a-z0-9]/i', '', $receipt['details']['Vehicle plate'] ?? ''));
            $base = date('Ymd-Hi', $receipt['at']) . '-' . ($plate !== '' ? $plate : strtoupper($receipt['type']));
            $number = $base;
            for ($sequence = 2; isset($used[$number]); $sequence++) {
                $number = $base . '-' . sprintf('%02d', $sequence);
            }
            $receipt['number'] = $number;
            $used[$number] = true;
        }
        unset($receipt);
    }
    unset($receipts);
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
        if (($booking['paid_amount_cents'] ?? 0) > 0) $details['Payment method'] = 'MyPark demo wallet';
        receipt_add($booking, $booking['user_id'], 'parking', (int) round($booking['fee'] * 100), $details, $oldest);
    }
    unset($booking);

    $_SESSION['demo_wallets'] ??= [];
    foreach ($_SESSION['demo_wallets'] as $userId => &$wallet) {
        foreach ($wallet['transactions'] as &$transaction) {
            if (($transaction['status'] ?? '') !== 'paid' || ($transaction['type'] ?? '') === 'parking') continue;
            $type = ($transaction['type'] ?? 'topup') === 'compound' ? 'compound' : 'topup';
            $details = $type === 'compound' ? [
                'Compound number' => $transaction['compound'],
                'Payment reference' => $transaction['reference'],
                'Payment method' => 'MyPark demo wallet',
            ] : (!empty($transaction['reference']) ? [
                'Payment reference' => $transaction['reference'],
                'Payment method' => 'PayPal Sandbox',
            ] : []);
            if ($type === 'compound' && !empty($transaction['plate'])) {
                $details['Vehicle plate'] = $transaction['plate'];
            }
            receipt_add($transaction, $userId, $type, (int) round($transaction['amount'] * 100), $details, $oldest);
        }
        unset($transaction);
    }
    unset($wallet);
    receipt_assign_numbers();
}

function receipt_parking_rows(int $userId, array $receipts, ?int $from, int $until): array {
    $remainingReceipts = array_column($receipts, null, 'id');
    $rows = [];
    foreach ($_SESSION['demo_bookings'] as $booking) {
        if ($booking['user_id'] !== $userId) continue;
        $at = receipt_date($booking['created_at'], 'Y-m-d H:i')?->getTimestamp() ?? 0;
        if ($from !== null && ($at < $from || $at >= $until)) continue;

        // Booking numbers can be reused after logout. The receipt ID joins only the original stay.
        $receiptId = $booking['receipt_id'] ?? '';
        $rows[] = ['at' => $at, 'booking' => $booking, 'receipt' => $remainingReceipts[$receiptId] ?? null];
        unset($remainingReceipts[$receiptId]);
    }
    foreach ($remainingReceipts as $receipt) {
        $rows[] = ['at' => $receipt['at'], 'booking' => null, 'receipt' => $receipt];
    }
    usort($rows, static fn($a, $b) => $b['at'] <=> $a['at']);
    return $rows;
}
