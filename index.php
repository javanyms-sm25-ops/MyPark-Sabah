<?php
require __DIR__ . '/includes/bootstrap.php';

if (me()) {
    $home = match (me()['role']) {
        'admin', 'superadmin' => 'admin/dashboard.php',
        'staff' => 'staff.php',
        default => 'dashboard.php',
    };
    header('Location: ' . $home);
    exit;
}

head('Home');
?>

<link rel="stylesheet" href="assets/css/home.css">

<section class="home-hero hero">
  <div class="home-hero__content">
    <p class="eyebrow"><?= te('SMART PARKING FOR SABAH') ?></p>
    <h1><?= te('Park smarter.') ?><br><span><?= te('Drive easier.') ?></span></h1>
    <p><?= te('Book your next parking spot in seconds, pay by the hour, and keep every trip smooth from arrival to departure.') ?></p>

    <div class="home-hero__actions">
      <a class="btn" href="pages/register.php"><?= te('Get started') ?></a>
      <a class="btn btn-ghost" href="pages/login.php"><?= te('Log in') ?></a>
    </div>

    <ul class="home-hero__stats" aria-label="MyPark metrics">
      <li><strong>2,400+</strong><span><?= te('Daily parkers') ?></span></li>
      <li><strong>18</strong><span><?= te('Smart zones') ?></span></li>
      <li><strong>4.9/5</strong><span><?= te('Customer rating') ?></span></li>
    </ul>
  </div>

  <div class="home-hero__visual">
    <div class="home-hero__panel home-hero__panel--primary">
      <div class="home-hero__chip"><?= te('Parking preview') ?></div>
      <div class="home-hero__location">
        <span class="home-hero__pin" aria-hidden="true">📍</span>
        <div>
          <strong>Kota Kinabalu Waterfront</strong>
          <small><?= te('12 spaces left (example)') ?></small>
        </div>
      </div>
      <div class="home-hero__price-row">
        <span><?= te('Regular parking') ?></span>
        <strong><?= te('RM {amount}/hour', ['amount' => number_format(PARKING_HOURLY_CENTS / 100, 2)]) ?></strong>
      </div>
    </div>

    <div class="home-hero__panel home-hero__panel--secondary">
      <p><?= te('Parking rates') ?></p>
      <div class="home-hero__mini-grid">
        <span><?= te('Daily maximum') ?></span>
        <strong>RM <?= e(number_format(PARKING_DAILY_CENTS / 100, 2)) ?></strong>
      </div>
      <div class="home-hero__mini-grid">
        <span><?= te('OKU parking') ?></span>
        <strong><?= te('Free') ?></strong>
      </div>
    </div>
  </div>
</section>

<section class="home-section">
  <div class="section-heading">
    <p class="eyebrow"><?= te('WHY MyPark') ?></p>
    <h2><?= te('Everything your trip needs in one place') ?></h2>
  </div>

  <div class="feature-grid">
    <article class="card feature-card">
      <div class="feature-card__icon">🚗</div>
      <h3><?= te('Saved vehicles') ?></h3>
      <p><?= te('Save your number plates so you can select a vehicle the next time you park.') ?></p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">📍</div>
      <h3><?= te('Smart locations') ?></h3>
      <p><?= te('Find parking close to stations, malls, hotels and major city destinations across Sabah.') ?></p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">💳</div>
      <h3><?= te('Fast payment') ?></h3>
      <p><?= te('Top up your wallet, pay by the hour and get receipts instantly with a cashless experience.') ?></p>
    </article>
  </div>
</section>

<section class="home-section home-section--alt">
  <div class="section-heading">
    <p class="eyebrow"><?= te('HOW IT WORKS') ?></p>
    <h2><?= te('Reserve in three simple steps') ?></h2>
  </div>

  <div class="steps-grid">
    <div class="card step-card">
      <span>1</span>
      <h3><?= te('Choose a location') ?></h3>
      <p><?= te('Select a parking zone near your destination and check availability.') ?></p>
    </div>

    <div class="card step-card">
      <span>2</span>
      <h3><?= te('Set your timing') ?></h3>
      <p><?= te('Pick your vehicle and duration, then confirm the details in one tap.') ?></p>
    </div>

    <div class="card step-card">
      <span>3</span>
      <h3><?= te('Enjoy the ride') ?></h3>
      <p><?= te('Use your QR e-ticket and get in and out without the usual parking hassle.') ?></p>
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

