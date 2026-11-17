<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if (me()['role'] === 'superadmin') {
    header('Location: admin/', true, 303);
    exit;
}

$userId = me()['id'];
require __DIR__ . '/includes/parking-booking.php';
$wallet = demo_wallet();
$customer = me()['role'] === 'user';
$vehicles = $customer ? saved_vehicles($userId) : [];
$defaultVehicle = $customer ? default_vehicle_id($userId) : null;
$resume = parking_resume_fields($_GET);
$requestedVehicle = $_GET['vehicle_id'] ?? '';
$draft = [
    'lot_id' => (string)($resume['lot_id'] ?? ''),
    'bay_type' => 'regular',
    'hours' => (string)($resume['hours'] ?? 1),
    'vehicle_id' => $vehicles ? ($defaultVehicle ?? '') : 'manual',
    'plate' => '',
    'save_vehicle' => false,
];
if (is_string($requestedVehicle) && isset($vehicles[$requestedVehicle])) {
    $draft['vehicle_id'] = $requestedVehicle;
}
$errors = [];
$bookingError = '';
$review = null;
$addVehicleError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        flash(cancel_booking((int) ($_POST['id'] ?? 0), $userId));
        header('Location: dashboard.php', true, 303);
        exit;
    }

    if ($action === 'add_booking_vehicle') {
        if (!$customer) {
            http_response_code(403);
            exit('Vehicle management is available to customers.');
        }
        $resume = parking_resume_fields($_POST);
        foreach ($resume as $key => $value) $draft[$key] = (string)$value;
        try {
            $added = change_saved_vehicle($userId, ['vehicle_action' => 'add'] + $_POST);
            flash($added['message']);
            header('Location: dashboard.php?' . http_build_query($resume + ['vehicle_id' => $added['id']]) . '#bookingPanel', true, 303);
            exit;
        } catch (InvalidArgumentException $error) {
            $addVehicleError = $error->getMessage();
            http_response_code(422);
        }
    } elseif ($action === 'review_parking') {
        unset($_SESSION['parking_reviews'][$userId]);
        $draft = parking_draft_from_post($_POST);
        $checked = validate_parking_draft(me(), $draft);
        $errors = $checked['errors'];
        if ($errors) {
            http_response_code(422);
        } else {
            $review = $checked['details'] + ['token' => bin2hex(random_bytes(32)), 'reviewed_at' => time()];
            $_SESSION['parking_reviews'][$userId] = [
                'token' => $review['token'],
                'draft' => $draft,
                'fee' => $review['fee'],
                'plate' => $review['plate'],
                'reviewed_at' => $review['reviewed_at'],
            ];
        }
    } elseif ($action === 'start_parking' || $action === 'edit_parking') {
        $pending = $_SESSION['parking_reviews'][$userId] ?? null;
        if ($pending === null || !hash_equals($pending['token'], $_POST['review_token'] ?? '')) {
            http_response_code(409);
            $bookingError = 'This review is no longer valid. Check your parking details again.';
        } elseif (isset($_POST['bay_type']) && $_POST['bay_type'] !== ($pending['draft']['bay_type'] ?? 'regular')) {
            http_response_code(422);
            $draft = $pending['draft'];
            $bookingError = 'Review your changes before starting parking.';
            unset($_SESSION['parking_reviews'][$userId]);
        } else {
            // PHP holds the session lock here, so a second submission cannot use the same review.
            unset($_SESSION['parking_reviews'][$userId]);
            $draft = $pending['draft'];
            if ($action === 'start_parking') {
                $checked = validate_parking_draft(me(), $draft);
                $errors = $checked['errors'];
                $details = $checked['details'];
                if (time() - $pending['reviewed_at'] >= 600) {
                    $bookingError = 'This review has expired. Review your parking details again.';
                } elseif (!$errors && ($details['fee'] !== $pending['fee'] || $details['plate'] !== $pending['plate'])) {
                    $bookingError = 'The fee or vehicle details changed. Review the updated details before starting.';
                } elseif (!$errors) {
                    start_parking(me(), $details);
                    flash('Parking session created. Your e-ticket is ready.');
                    header('Location: dashboard.php', true, 303);
                    exit;
                }
                http_response_code(409);
            }
        }
    } else {
        http_response_code(400);
        $bookingError = 'Review your parking details before starting.';
    }
}

$lots = $_SESSION['demo_lots'];
$occupiedByLot = parking_occupancy_by_lot($_SESSION['demo_bookings']);
$bookings = array_values(array_filter($_SESSION['demo_bookings'], fn($booking) => $booking['user_id'] === $userId));
$activeBookings = array_filter($bookings, fn($booking) => $booking['status'] === 'active');
$completedBookings = array_filter($bookings, fn($booking) => $booking['status'] === 'completed');

head('Sabah Parking');
?>

<section class="hero fade-in dashboard-hero">
  <div class="dashboard-hero__content">
  <p class="eyebrow"><?= te('WELCOME BACK, {name}', ['name' => me()['name']]) ?></p>
  <h1><?= te('Find your space.') ?><br><span><?= te('Enjoy the journey.') ?></span></h1>
  <p><?= te('Discover convenient parking in Kota Kinabalu, Sandakan, Tawau and beyond.') ?></p>
  <a class="btn" href="#locations"><?= te('Explore locations') ?></a>
  </div>
  <aside class="dashboard-wallet" aria-labelledby="dashboard-wallet-title"
    data-wallet-user="<?= e($userId) ?>"
    data-wallet-balance="RM <?= e(number_format($wallet['balance'], 2)) ?>">
    <p id="dashboard-wallet-title" class="dashboard-wallet__label"><?= te('Wallet balance') ?></p>
    <div class="dashboard-wallet__row">
      <strong id="dashboard-wallet-amount" class="dashboard-wallet__amount" aria-live="polite" aria-atomic="true">RM ••••</strong>
      <button type="button" class="dashboard-wallet__toggle" aria-label="<?= te('Show wallet balance') ?>"
        aria-controls="dashboard-wallet-amount" hidden>
        <svg class="dashboard-wallet__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
          <circle cx="12" cy="12" r="3" />
          <path class="dashboard-wallet__slash" d="M3 3 21 21" />
        </svg>
      </button>
    </div>
    <a class="dashboard-wallet__link" href="wallet.php"><?= te('Manage wallet') ?> <span aria-hidden="true">→</span></a>
  </aside>
</section>

<section class="stats">
  <div class="card"><small><?= te('Active parking') ?></small><b><?= count($activeBookings) ?></b></div>
  <div class="card"><small><?= te('Completed visits') ?></small><b><?= count($completedBookings) ?></b></div>
  <div class="card"><small><?= te('Reward points') ?></small><b><?= count($bookings) * 25 ?></b></div>
</section>

<section class="row card filters" aria-label="Search parking locations">
  <input id="lotSearch" placeholder="<?= te('Search city or parking lot') ?>">
  <select id="cityFilter">
    <option value=""><?= te('All Sabah locations') ?></option>
    <?php foreach (array_unique(array_column($lots, 'city')) as $city): ?>
      <option><?= e($city) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="priceFilter">
    <option value=""><?= te('Any hourly rate') ?></option>
    <option value="low"><?= te('Under RM 2.50') ?></option>
    <option value="high"><?= te('RM 2.50 and above') ?></option>
  </select>
</section>

<h2 id="locations"><?= te('Popular parking locations') ?></h2>
<section class="lot-grid">
  <?php foreach ($lots as $lot): ?>
    <article class="card lot-card" data-city="<?= e($lot['city']) ?>" data-name="<?= e(strtolower($lot['name'] . ' ' . $lot['city'])) ?>" data-rate="<?= $lot['rate'] ?>">
      <div class="lot-cover">📍 <span>Sabah, Malaysia</span><button type="button" class="heart" data-wish="<?= $lot['id'] ?>">♡</button></div>
      <h3><?= e($lot['name']) ?></h3>
      <p><?= e($lot['address']) ?> · <?= e($lot['city']) ?></p>
      <p class="rate">RM <?= number_format($lot['rate'], 2) ?><small> / hour</small></p>
      <?php $bays = parking_bay_counts($lot, $occupiedByLot[$lot['id']] ?? []); ?>
      <?php if ($bays['regular']['capacity'] === null): ?>
        <p><?= te('Capacity unavailable') ?></p>
      <?php else: ?>
        <p><?= te('Regular bays available: {count}', ['count' => $bays['regular']['available']]) ?>
          <?php if ($bays['oku']['capacity'] > 0): ?> · <?= te('OKU bays available: {count}', ['count' => $bays['oku']['available']]) ?><?php endif; ?>
        </p>
      <?php endif; ?>
      <a class="btn" href="https://www.google.com/maps/search/?api=1&amp;query=<?= $lot['lat'] ?>,<?= $lot['lng'] ?>" target="_blank" rel="noopener"><?= te('View map') ?></a>
      <a class="btn" data-book-lot="<?= $lot['id'] ?>" href="dashboard.php?lot_id=<?= $lot['id'] ?>#bookingPanel"><?= te('Select location') ?></a>
    </article>
  <?php endforeach; ?>
</section>

<?php require __DIR__ . '/includes/booking-panel.php'; ?>

<p class="section-link"><a class="btn" href="history.php"><?= te('View parking history') ?></a></p>
<h2><?= te('Sabah parking map') ?></h2>
<iframe class="map" loading="lazy" src="https://maps.google.com/maps?q=Sabah%20Malaysia&amp;t=&amp;z=7&amp;ie=UTF8&amp;iwloc=&amp;output=embed" title="Map of Sabah"></iframe>

<?php foot(); ?>
