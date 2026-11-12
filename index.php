<?php
require 'includes.php';

if (me()) {
    header('Location: dashboard.php');
    exit;
}

head('Home');
?>

<section class="home-hero hero">
  <div class="home-hero__content">
    <p class="eyebrow">SMART PARKING FOR SABAH</p>
    <h1>Park smarter.<br><span>Drive easier.</span></h1>
    <p>Book your next parking spot in seconds, pay by the hour, and keep every trip smooth from arrival to departure.</p>

    <div class="home-hero__actions">
      <a class="btn" href="register.php">Get started</a>
      <a class="btn btn-ghost" href="login.php">Log in</a>
    </div>

    <ul class="home-hero__stats" aria-label="ParkEasy metrics">
      <li><strong>2,400+</strong><span>Daily parkers</span></li>
      <li><strong>18</strong><span>Smart zones</span></li>
      <li><strong>4.9/5</strong><span>Customer rating</span></li>
    </ul>
  </div>

  <div class="home-hero__visual" aria-label="Parking overview card">
    <div class="home-hero__panel home-hero__panel--primary">
      <div class="home-hero__chip">Live availability</div>
      <div class="home-hero__location">
        <span class="home-hero__pin">📍</span>
        <div>
          <strong>Kota Kinabalu Waterfront</strong>
          <small>12 spaces left</small>
        </div>
      </div>
      <div class="home-hero__price-row">
        <span>From</span>
        <strong>RM 3.00/hr</strong>
      </div>
    </div>

    <div class="home-hero__panel home-hero__panel--secondary">
      <p>Tonight</p>
      <div class="home-hero__mini-grid">
        <span>Premium</span>
        <strong>RM 18</strong>
      </div>
      <div class="home-hero__mini-grid">
        <span>Covered</span>
        <strong>RM 12</strong>
      </div>
    </div>
  </div>
</section>

<section class="home-section">
  <div class="section-heading">
    <p class="eyebrow">WHY PARKEASY</p>
    <h2>Everything your trip needs in one place</h2>
  </div>

  <div class="feature-grid">
    <article class="card feature-card">
      <div class="feature-card__icon">🚗</div>
      <h3>Instant booking</h3>
      <p>Reserve a parking bay in minutes and keep your itinerary moving without the stress of last-minute parking hunts.</p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">📍</div>
      <h3>Smart locations</h3>
      <p>Find parking close to stations, malls, hotels and major city destinations across Sabah.</p>
    </article>

    <article class="card feature-card">
      <div class="feature-card__icon">💳</div>
      <h3>Fast payment</h3>
      <p>Top up your wallet, pay by the hour and get receipts instantly with a cashless experience.</p>
    </article>
  </div>
</section>

<section class="home-section home-section--alt">
  <div class="section-heading">
    <p class="eyebrow">HOW IT WORKS</p>
    <h2>Reserve in three simple steps</h2>
  </div>

  <div class="steps-grid">
    <div class="card step-card">
      <span>1</span>
      <h3>Choose a location</h3>
      <p>Select a parking zone near your destination and compare rates instantly.</p>
    </div>

    <div class="card step-card">
      <span>2</span>
      <h3>Set your timing</h3>
      <p>Pick your vehicle and duration, then confirm the details in one tap.</p>
    </div>

    <div class="card step-card">
      <span>3</span>
      <h3>Enjoy the ride</h3>
      <p>Use your QR e-ticket and get in and out without the usual parking hassle.</p>
    </div>
  </div>
</section>

<section class="home-section">
  <div class="promo-panel card">
    <div>
      <p class="eyebrow">READY TO START?</p>
      <h2>Parking that feels as easy as the rest of your day.</h2>
    </div>
    <div class="promo-panel__actions">
      <a class="btn" href="register.php">Create account</a>
      <a class="btn btn-ghost btn-ghost--dark" href="login.php">Sign in</a>
    </div>
  </div>
</section>

<?php foot(); ?>

