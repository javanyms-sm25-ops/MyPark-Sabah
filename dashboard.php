<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if (in_array(me()['role'], ['admin', 'superadmin'], true)) {
    header('Location: admin/dashboard.php', true, 303);
    exit;
}
if (me()['role'] === 'staff') {
    header('Location: staff.php', true, 303);
    exit;
}

$userId = me()['id'];
require_once __DIR__ . '/includes/parking-booking.php';
$vehicleSelectionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['add_booking_vehicle', 'review_parking', 'start_parking', 'edit_parking'], true)) {
        require __DIR__ . '/parking-details.php';
        return;
    }
    check_csrf();
    if ($action === 'select_vehicle' && me()['role'] === 'user') {
        try {
            $updated = change_saved_vehicle($userId, ['vehicle_action' => 'select', 'vehicle_id' => $_POST['vehicle_id'] ?? '']);
            flash($updated['message']);
            header('Location: dashboard.php#selected-vehicle', true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            $vehicleSelectionError = $error->getMessage();
            http_response_code($error->getCode() ?: 422);
        }
    } else {
        if ($action !== 'cancel') {
            http_response_code(400);
            exit;
        }
        header('Location: receipts.php?type=parking#parking-sessions', true, 308);
        exit;
    }
}

$resume = parking_resume_fields($_GET);
if (is_string($_GET['vehicle_id'] ?? null)) {
    $resume['vehicle_id'] = $_GET['vehicle_id'];
}
if ($resume) {
    header('Location: parking-details.php?' . http_build_query($resume), true, 303);
    exit;
}

$wallet = demo_wallet();
$lots = active_parking_locations();
$locationPhotos = [
    'Kota Kinabalu Waterfront' => 'kota-kinabalu-waterfront.webp',
    'Sandakan Central Parking' => 'sandakan-central-parking.webp',
    'Tawau Town Centre' => 'tawau-town-centre.webp',
];
$occupiedByLot = parking_occupancy_by_lot(MyParkData::$records['demo_bookings']);
$bookings = array_values(array_filter(MyParkData::$records['demo_bookings'], fn($booking) => $booking['user_id'] === $userId));
$completedBookings = array_filter($bookings, fn($booking) => $booking['status'] === 'completed');
$vehicleState = ['vehicles' => [], 'selected_id' => null, 'default_id' => null];
if (me()['role'] === 'user') {
    header('Cache-Control: private, no-store');
    try {
        $vehicleState = saved_vehicle_state($userId);
    } catch (RuntimeException $error) {
        $vehicleSelectionError = $error->getMessage();
        http_response_code(503);
    }
}

head('Sabah Parking');
?>

<?php require __DIR__ . '/includes/active-parking-card.php'; ?>

<section class="hero fade-in dashboard-hero">
  <div class="dashboard-hero__content">
  <p class="eyebrow"><?= te('WELCOME BACK, {name}', ['name' => me()['name']]) ?></p>
  <h1><?= te('Find your space.') ?><br><span><?= te('Enjoy the journey.') ?></span></h1>
  <p><?= te('Discover convenient parking in Kota Kinabalu, Sandakan, Tawau and beyond.') ?></p>
  <div class="row">
    <a class="btn" href="#locations"><?= te('Explore locations') ?></a>
    <?php if (in_array(me()['role'], ['user', 'admin'], true)): ?>
      <a class="btn" href="find_parking.php"><?= te('Find parking') ?></a>
    <?php endif; ?>
  </div>
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
  <div class="card"><small><?= te('Completed visits') ?></small><b><?= count($completedBookings) ?></b></div>
  <div class="card"><small><?= te('Reward points') ?></small><b><?= count($bookings) * 25 ?></b></div>
</section>

<?php if (me()['role'] === 'user') require __DIR__ . '/includes/vehicle-selector.php'; ?>

<section class="row card filters" aria-label="Search parking locations">
  <input id="lotSearch" placeholder="<?= te('Search city or parking lot') ?>">
  <select id="cityFilter">
    <option value=""><?= te('All Sabah locations') ?></option>
    <?php foreach (array_unique(array_column($lots, 'city')) as $city): ?>
      <option><?= e($city) ?></option>
    <?php endforeach; ?>
  </select>
</section>

<div class="dashboard-location-heading">
  <h2 id="locations"><?= te('Popular parking locations') ?></h2>
  <a class="btn btn-secondary" href="find_parking.php?favourites=1" data-view-favourites hidden><?= te('View favourites') ?></a>
</div>
<section class="lot-grid dashboard-location-grid" aria-labelledby="locations">
  <?php foreach ($lots as $lot): ?>
    <?php $photo = $locationPhotos[$lot['name']] ?? null; ?>
    <article class="card lot-card" data-city="<?= e($lot['city']) ?>" data-name="<?= e(strtolower($lot['name'] . ' ' . $lot['city'])) ?>">
      <div class="lot-cover">
        <?php if ($photo !== null): ?>
          <img class="lot-photo" src="assets/images/<?= e($photo) ?>" alt="" loading="lazy" decoding="async">
        <?php endif; ?>
        <span class="lot-region"><?= te('Sabah, Malaysia') ?></span>
        <button type="button" class="heart" data-wish="<?= e($lot['id']) ?>" aria-pressed="false" hidden
                aria-label="<?= te('Favourite {location}', ['location' => $lot['name']]) ?>">♡</button>
      </div>
      <h3><?= e($lot['name']) ?></h3>
      <p><?= e($lot['address']) ?> · <?= e($lot['city']) ?></p>
      <p class="rate"><?= e(parking_price_label()) ?></p>
      <?php $bays = parking_bay_counts($lot, $occupiedByLot[$lot['id']] ?? []); ?>
      <?php if ($bays['regular']['capacity'] === null): ?>
        <p><?= te('Capacity unavailable') ?></p>
      <?php else: ?>
        <p><?= te('Regular bays available: {count}', ['count' => $bays['regular']['available']]) ?>
          <?php if ($bays['oku']['capacity'] > 0): ?> · <?= te('OKU bays available: {count}', ['count' => $bays['oku']['available']]) ?> (<?= te('Free') ?>)<?php endif; ?>
        </p>
      <?php endif; ?>
      <div class="lot-actions">
        <a class="btn btn-secondary" href="https://www.google.com/maps/search/?api=1&amp;query=<?= $lot['lat'] ?>,<?= $lot['lng'] ?>" target="_blank" rel="noopener"><?= te('View map') ?></a>
        <a class="btn" href="parking-details.php?lot_id=<?= e($lot['id']) ?>"><?= te('Select location') ?></a>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<p class="section-link"><a class="btn" href="receipts.php?type=parking#parking-sessions"><?= te('View parking sessions') ?></a></p>
<h2><?= te('Sabah parking map') ?></h2>
<iframe class="map" loading="lazy" src="https://maps.google.com/maps?q=Sabah%20Malaysia&amp;t=&amp;z=7&amp;ie=UTF8&amp;iwloc=&amp;output=embed" title="Map of Sabah"></iframe>

<script src="assets/js/parking-favourites.js" defer></script>
<?php foot(); ?>
