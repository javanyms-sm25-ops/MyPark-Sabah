<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (me()) {
    $home = match (me()['role']) {
        'admin', 'superadmin' => 'admin/dashboard.php',
        'staff' => 'staff.php',
        default => 'dashboard.php',
    };
    header('Location: ' . $home);
    exit;
}

require_once __DIR__ . '/includes/parking-capacity.php';

$lots = MyParkData::$records['demo_lots'];
$locationCount = count($lots);
$previewLots = array_slice($lots, 0, 3);
$occupiedByLot = parking_occupancy_by_lot(MyParkData::$records['demo_bookings']);
$rates = [
    'Regular parking' => t('RM {amount}/hour', ['amount' => number_format(PARKING_HOURLY_CENTS / 100, 2)]),
    'Maximum per session' => 'RM ' . number_format(PARKING_DAILY_CENTS / 100, 2),
    'OKU parking' => t('Free'),
];

header('Cache-Control: private, no-store');
head('Home');
?>

<link rel="stylesheet" href="assets/css/home.css">

<section class="home-hero hero">
  <div class="home-hero__content">
    <p class="eyebrow"><?= te('SMART PARKING FOR SABAH') ?></p>
    <h1><?= te('Park smarter.') ?><br><span><?= te('Drive easier.') ?></span></h1>
    <p><?= te('Find parking across Sabah, check the rates, and start a session when you arrive.') ?></p>

    <div class="home-hero__actions">
      <a class="btn" href="pages/register.php"><?= te('Create account') ?></a>
      <a class="btn btn-ghost" href="pages/login.php"><?= te('Log in') ?></a>
    </div>

    <?php if ($locationCount > 0): ?>
      <p class="home-hero__coverage"><?= te($locationCount === 1 ? '{count} parking location in MyPark' : '{count} parking locations in MyPark', ['count' => $locationCount]) ?></p>
    <?php endif; ?>
  </div>

  <div class="home-hero__visual">
    <section class="home-hero__panel home-hero__panel--primary" aria-labelledby="home-availability-title">
      <h2 class="home-hero__panel-title" id="home-availability-title"><?= te('Parking availability') ?></h2>
      <?php if ($previewLots): ?>
        <ul class="home-hero__locations">
          <?php foreach ($previewLots as $lot):
              $available = parking_bay_counts($lot, $occupiedByLot[$lot['id']] ?? [])['regular']['available'];
              $availability = match ($available) {
                  null => t('Capacity unavailable'),
                  0 => t('No regular bays available'),
                  default => t('Regular bays available: {count}', ['count' => $available]),
              };
          ?>
            <li class="home-hero__location">
              <span class="home-hero__pin" aria-hidden="true">📍</span>
              <div>
                <strong><?= e($lot['name']) ?></strong>
                <small><?= e($availability) ?></small>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="home-hero__note"><?= te('Counts reflect active MyPark sessions when this page loads.') ?></p>
        <?php if ($locationCount > count($previewLots)): ?>
          <a class="home-hero__more" href="pages/login.php"><?= te('Log in to see all parking locations') ?> <span aria-hidden="true">→</span></a>
        <?php endif; ?>
      <?php else: ?>
        <p class="home-hero__note"><?= te('No parking locations yet.') ?></p>
      <?php endif; ?>
    </section>

    <section class="home-hero__panel home-hero__panel--secondary" aria-labelledby="home-rates-title">
      <h2 class="home-hero__panel-title" id="home-rates-title"><?= te('Parking rates') ?></h2>
      <dl class="home-hero__rates">
        <?php foreach ($rates as $label => $rate): ?>
          <div class="home-hero__mini-grid">
            <dt><?= te($label) ?></dt>
            <dd><?= e($rate) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <p class="home-hero__note"><?= te('Free OKU parking requires approved MyPark verification and an available OKU bay.') ?></p>
    </section>
  </div>
</section>

<section class="home-section">
  <div class="section-heading">
    <p class="eyebrow"><?= te('WHY MyPark') ?></p>
    <h2><?= te('Manage your parking in one place') ?></h2>
  </div>

  <div class="feature-grid">
    <article class="card feature-card">
      <div class="feature-card__icon">🚗</div>
      <h3><?= te('Saved vehicles') ?></h3>
      <p><?= te('Save your number plates so you can select a vehicle the next time you park.') ?></p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">📍</div>
      <h3><?= te('Parking locations') ?></h3>
      <p><?= te('Check MyPark bay availability before starting a parking session.') ?></p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">💳</div>
      <h3><?= te('Wallet and receipts') ?></h3>
      <p><?= te('Pay for regular parking with your MyPark wallet and find your receipts in your account.') ?></p>
    </article>
  </div>
</section>

<section class="home-section home-section--alt">
  <div class="section-heading">
    <p class="eyebrow"><?= te('HOW IT WORKS') ?></p>
    <h2><?= te('Start parking in three steps') ?></h2>
  </div>

  <div class="steps-grid">
    <div class="card step-card">
      <span>1</span>
      <h3><?= te('Choose a location') ?></h3>
      <p><?= te('Check bay availability and select a location near your destination.') ?></p>
    </div>

    <div class="card step-card">
      <span>2</span>
      <h3><?= te('Set your duration') ?></h3>
      <p><?= te('Choose your vehicle and how long you plan to park. Review the fee before you confirm.') ?></p>
    </div>

    <div class="card step-card">
      <span>3</span>
      <h3><?= te('Start parking') ?></h3>
      <p><?= te('Confirm to start parking immediately and open your e-ticket. Use your MyPark wallet to pay for regular parking.') ?></p>
    </div>
  </div>
</section>

<section class="home-section">
  <div class="promo-panel card">
    <div>
      <p class="eyebrow"><?= te('READY TO START?') ?></p>
      <h2><?= te('Parking that feels as easy as the rest of your day.') ?></h2>
    </div>
    <div class="promo-panel__actions">
      <a class="btn" href="pages/register.php"><?= te('Create account') ?></a>
      <a class="btn btn-ghost btn-ghost--dark" href="pages/login.php"><?= te('Sign in') ?></a>
    </div>
  </div>
</section>

<?php foot(); ?>

