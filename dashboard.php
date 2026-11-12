<?php
require 'includes.php';
require_login();

$userId = me()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'book') {
        $lotId = (int) ($_POST['lot_id'] ?? 0);
        $hours = (int) ($_POST['hours'] ?? 0);
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $selectedLot = null;

        foreach ($_SESSION['demo_lots'] as $lot) {
            if ($lot['id'] === $lotId) $selectedLot = $lot;
        }

        if (!$selectedLot) {
            flash('Parking location not found.');
        } elseif (!preg_match('/^[A-Z]{1,3} ?\d{1,4} ?[A-Z]?$/', $plate)) {
            flash('Enter a valid plate number, for example SAB 1234.');
        } elseif ($hours < 1 || $hours > 24) {
            flash('Duration must be 1-24 hours.');
        } else {
            $bookingId = count($_SESSION['demo_bookings']) + 1;
            $_SESSION['demo_bookings'][] = [
                'id' => $bookingId,
                'user_id' => $userId,
                'user' => me()['name'],
                'lot_id' => $lotId,
                'lot' => $selectedLot['name'],
                'city' => $selectedLot['city'],
                'slot' => 'P' . str_pad((string) $bookingId, 2, '0', STR_PAD_LEFT),
                'plate' => $plate,
                'hours' => $hours,
                'fee' => $hours * $selectedLot['rate'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i'),
                'expires_at' => time() + ($hours * 3600)
            ];
            flash('Parking reserved. Your e-ticket is ready.');
        }
    }

    if ($action === 'cancel') {
        foreach ($_SESSION['demo_bookings'] as &$booking) {
            if ($booking['id'] === (int) ($_POST['id'] ?? 0) && $booking['user_id'] === $userId) {
                $booking['status'] = 'cancelled';
            }
        }
        unset($booking);
        flash('Booking cancelled.');
    }

    header('Location: dashboard.php');
    exit;
}

$lots = $_SESSION['demo_lots'];
$bookings = array_values(array_filter($_SESSION['demo_bookings'], fn($booking) => $booking['user_id'] === $userId));
$activeBookings = array_filter($bookings, fn($booking) => $booking['status'] === 'active');
$completedBookings = array_filter($bookings, fn($booking) => $booking['status'] === 'completed');

head('Sabah Parking');
?>

<section class="hero fade-in dashboard-hero">
  <div class="dashboard-hero__content">
  <p class="eyebrow">WELCOME BACK, <?= e(me()['name']) ?></p>
  <h1>Find your space.<br><span>Enjoy the journey.</span></h1>
  <p>Discover convenient parking in Kota Kinabalu, Sandakan, Tawau and beyond.</p>
  <a class="btn" href="#locations">Explore locations</a>
  </div>
  <aside class="dashboard-wallet" aria-labelledby="dashboard-wallet-title"
    data-wallet-user="<?= e($userId) ?>"
    data-wallet-balance="RM <?= e(number_format($_SESSION['demo_wallet_balance'], 2)) ?>">
    <p id="dashboard-wallet-title" class="dashboard-wallet__label">Wallet balance</p>
    <div class="dashboard-wallet__row">
      <strong id="dashboard-wallet-amount" class="dashboard-wallet__amount" aria-live="polite" aria-atomic="true">RM ••••</strong>
      <button type="button" class="dashboard-wallet__toggle" aria-label="Show wallet balance"
        aria-controls="dashboard-wallet-amount" hidden>
        <svg class="dashboard-wallet__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
          <circle cx="12" cy="12" r="3" />
          <path class="dashboard-wallet__slash" d="M3 3 21 21" />
        </svg>
      </button>
    </div>
    <a class="dashboard-wallet__link" href="wallet.php">Manage wallet <span aria-hidden="true">→</span></a>
  </aside>
</section>

<section class="stats">
  <div class="card"><small>Active parking</small><b><?= count($activeBookings) ?></b></div>
  <div class="card"><small>Completed visits</small><b><?= count($completedBookings) ?></b></div>
  <div class="card"><small>Reward points</small><b><?= count($bookings) * 25 ?></b></div>
</section>

<section class="row card filters" aria-label="Search parking locations">
  <input id="lotSearch" placeholder="Search city or parking lot">
  <select id="cityFilter">
    <option value="">All Sabah locations</option>
    <?php foreach (array_unique(array_column($lots, 'city')) as $city): ?>
      <option><?= e($city) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="priceFilter">
    <option value="">Any hourly rate</option>
    <option value="low">Under RM 2.50</option>
    <option value="high">RM 2.50 and above</option>
  </select>
</section>

<h2 id="locations">Popular parking locations</h2>
<section class="lot-grid">
  <?php foreach ($lots as $lot): ?>
    <article class="card lot-card" data-city="<?= e($lot['city']) ?>" data-name="<?= e(strtolower($lot['name'] . ' ' . $lot['city'])) ?>" data-rate="<?= $lot['rate'] ?>">
      <div class="lot-cover">📍 <span>Sabah, Malaysia</span><button type="button" class="heart" data-wish="<?= $lot['id'] ?>">♡</button></div>
      <h3><?= e($lot['name']) ?></h3>
      <p><?= e($lot['address']) ?> · <?= e($lot['city']) ?></p>
      <p class="rate">RM <?= number_format($lot['rate'], 2) ?><small> / hour</small></p>
      <p><?= $lot['capacity'] ?> spaces · <span class="tag available">Available</span></p>
      <a class="btn" href="https://www.google.com/maps/search/?api=1&amp;query=<?= $lot['lat'] ?>,<?= $lot['lng'] ?>" target="_blank" rel="noopener">View map</a>
      <button type="button" data-book-lot="<?= $lot['id'] ?>" data-name="<?= e($lot['name']) ?>" data-rate="<?= $lot['rate'] ?>">Reserve parking</button>
    </article>
  <?php endforeach; ?>
</section>

<section class="card" id="bookingPanel">
  <h2>Reserve a parking space</h2>
  <ol class="booking-steps">
    <li><b>1</b> Choose a location</li>
    <li><b>2</b> Add vehicle details</li>
    <li><b>3</b> Confirm reservation</li>
  </ol>
  <form method="post" id="bookForm">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="book">
    <input type="hidden" name="lot_id" id="lot_id">
    <p>Selected: <b id="lotLabel">Choose a location above</b></p>
    <label>Vehicle plate<input name="plate" id="plate" placeholder="SAB 1234" required></label>
    <label>Duration (hours)<input type="number" name="hours" id="hours" min="1" max="24" value="1" required></label>
    <p>Estimated fee: <b id="fee">RM 0.00</b></p>
    <button>Confirm reservation</button>
  </form>
</section>

<p class="section-link"><a class="btn" href="history.php">View parking history</a></p>
<h2>Sabah parking map</h2>
<iframe class="map" loading="lazy" src="https://maps.google.com/maps?q=Sabah%20Malaysia&amp;t=&amp;z=7&amp;ie=UTF8&amp;iwloc=&amp;output=embed" title="Map of Sabah"></iframe>

<?php foot(); ?>
