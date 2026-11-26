<?php

const RECEIPT_TYPES = [
    'parking' => 'Parking',
    'compound' => 'Compounds',
    'topup' => 'Wallet top-ups',
];

const RECEIPT_WINDOW_DAYS = 30;

function receipt_window(): array {
    $today = new DateTimeImmutable('today');
    return [$today->modify('-' . (RECEIPT_WINDOW_DAYS - 1) . ' days'), $today];
}

function receipt_available_through(int $timestamp): DateTimeImmutable {
    return (new DateTimeImmutable())->setTimestamp($timestamp)->modify('+' . (RECEIPT_WINDOW_DAYS - 1) . ' days');
}

function receipt_for_owner(int $userId, mixed $id): ?array {
    if (!is_string($id) || !preg_match('/\A[0-9A-F]{16}\z/', $id)) return null;
    $receipt = MyParkData::$records['demo_receipts'][$userId][$id] ?? null;
    [$firstDay] = receipt_window();
    return $receipt && $receipt['at'] >= $firstDay->getTimestamp() ? $receipt : null;
}

function receipt_url(array $receipt): string {
    return 'receipts.php?' . http_build_query(['type' => $receipt['type'], 'id' => $receipt['id']]);
}

function receipt_checkout_url(int $userId, mixed $checkout): ?string {
    if (!is_array($checkout) || ($checkout['user_id'] ?? null) !== $userId
        || empty($checkout['credited']) || empty($checkout['order_id'])) {
        return null;
    }

    receipt_sync();
    foreach (MyParkData::$records['demo_wallets'][$userId]['transactions'] ?? [] as $transaction) {
        if (($transaction['type'] ?? 'topup') !== 'topup' || ($transaction['status'] ?? '') !== 'paid'
            || ($transaction['reference'] ?? '') !== $checkout['order_id']) {
            continue;
        }
        $receipt = receipt_for_owner($userId, $transaction['receipt_id'] ?? null);
        return $receipt ? receipt_url($receipt) : null;
    }

    return null;
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
    } while (isset(MyParkData::$records['demo_receipts'][$userId][$id]));
    MyParkData::$records['demo_receipts'][$userId][$id] = [
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
    foreach (MyParkData::$records['demo_receipts'] as $receipts) {
        foreach (array_column($receipts, 'number') as $number) $used[$number] = true;
    }

    foreach (MyParkData::$records['demo_receipts'] as &$receipts) {
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
    MyParkData::$records['demo_receipts'] ??= [];

    foreach (MyParkData::$records['demo_receipts'] as $userId => $receipts) {
        $recent = array_filter($receipts, static fn($receipt) => $receipt['at'] >= $oldest);
        if ($recent) {
            MyParkData::$records['demo_receipts'][$userId] = $recent;
        } else {
            unset(MyParkData::$records['demo_receipts'][$userId]);
        }
    }

    foreach (MyParkData::$records['demo_bookings'] as &$booking) {
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

    MyParkData::$records['demo_wallets'] ??= [];
    foreach (MyParkData::$records['demo_wallets'] as $userId => &$wallet) {
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
    foreach (MyParkData::$records['demo_bookings'] as $booking) {
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
