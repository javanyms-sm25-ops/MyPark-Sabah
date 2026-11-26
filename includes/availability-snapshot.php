<?php

require_once __DIR__ . '/demo-session.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/parking-capacity.php';

function mypark_availability_snapshot(): array {
    start_demo_session(false);
    $session = $_SESSION;
    session_write_close();
    if (!isset($session['user'])) throw new RuntimeException('Please sign in again.', 401);

    $snapshot = MyParkData::readParkingState($session);
    $snapshot['lots'] = active_parking_locations($snapshot['lots']);
    $checkedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $now = $checkedAt->getTimestamp();
    $snapshot['bookings'] = array_filter($snapshot['bookings'], static function (array $booking) use ($now): bool {
        if ($booking['status'] !== 'active') return false;
        $expiry = booking_expiry($booking);
        return $expiry === null || $expiry > $now;
    });
    $snapshot['checkedAt'] = $checkedAt->format(DATE_ATOM);
    return $snapshot;
}
