<?php

const PARKING_HOURLY_CENTS = 100;
const PARKING_DAILY_CENTS = 800;

function parking_fee(int $hours, string $bayType): float {
    if ($bayType === 'oku') return 0.0;
    return min($hours * PARKING_HOURLY_CENTS, PARKING_DAILY_CENTS) / 100;
}

function parking_price_label(): string {
    return t('RM {hourly}/hour · RM {daily} daily maximum', [
        'hourly' => number_format(PARKING_HOURLY_CENTS / 100, 2),
        'daily' => number_format(PARKING_DAILY_CENTS / 100, 2),
    ]);
}
