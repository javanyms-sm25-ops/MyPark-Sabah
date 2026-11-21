<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$role = me()['role'];
if ($role === 'superadmin' || $role === 'staff') {
    header('Location: ' . ($role === 'superadmin' ? 'admin/' : 'staff.php'), true, 303);
    exit;
}

require_once __DIR__ . '/includes/parking-capacity.php';
$lots = $_SESSION['demo_lots'];
$occupiedByLot = parking_occupancy_by_lot($_SESSION['demo_bookings']);
$mapsConfigPath = __DIR__ . '/config/maps.local.php';
$mapsConfig = is_file($mapsConfigPath) ? require $mapsConfigPath : [];

head('Find parking');
?>
<link rel="stylesheet" href="assets/css/find-parking.css">
<h1><?= te('Find parking') ?></h1>
<div class="find-parking" data-find-parking
     data-maps-key="<?= e($mapsConfig['api_key'] ?? '') ?>"
     data-map-id="<?= e($mapsConfig['map_id'] ?? 'DEMO_MAP_ID') ?>">
  <section class="find-parking-map-panel" aria-label="<?= te('Parking map') ?>">
    <div class="find-parking-map" id="find-parking-map" hidden></div>
    <div class="find-parking-locate">
      <button type="button" data-use-location aria-controls="find-parking-map find-parking-list" hidden><?= te('Use my location') ?></button>
      <p class="find-parking-location-status" data-location-status role="status"></p>
    </div>
    <p class="find-parking-map-message" data-map-message role="status"></p>
    <noscript><p class="find-parking-map-message"><?= te('Enable JavaScript to use the parking map.') ?></p></noscript>
  </section>
  <section class="find-parking-results" aria-labelledby="find-parking-heading">
    <div class="find-parking-search">
      <h2 id="find-parking-heading"><?= te('Parking locations') ?></h2>
      <label for="find-parking-query"><?= te('Search parking locations') ?></label>
      <input id="find-parking-query" type="search" placeholder="<?= te('Search name, city or address') ?>"
             autocomplete="off" aria-controls="find-parking-list" disabled>
    </div>
    <div class="find-parking-list-wrap">
      <p data-parking-empty role="status"<?= $lots ? ' hidden' : '' ?>>
        <?= te($lots ? 'No matching parking locations.' : 'No parking locations yet.') ?>
      </p>
      <ul class="find-parking-list" id="find-parking-list">
        <?php foreach ($lots as $lot): ?>
          <?php
          $lat = filter_var($lot['lat'] ?? null, FILTER_VALIDATE_FLOAT);
          $lng = filter_var($lot['lng'] ?? null, FILTER_VALIDATE_FLOAT);
          $hasPosition = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180;
          $bays = parking_bay_counts($lot, $occupiedByLot[$lot['id']] ?? []);
          ?>
          <li class="find-parking-location" data-parking-location
              data-search="<?= e($lot['name'] . ' ' . $lot['address'] . ' ' . $lot['city']) ?>"
              <?php if ($hasPosition): ?>data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>"<?php endif; ?>>
            <h3><?= e($lot['name']) ?></h3>
            <p class="find-parking-address"><?= e($lot['address']) ?> · <?= e($lot['city']) ?></p>
            <?php if ($hasPosition): ?><p class="find-parking-distance" data-parking-distance hidden></p><?php endif; ?>
            <p class="find-parking-rate">RM <?= number_format($lot['rate'], 2) ?> <?= te('/ hour') ?></p>
            <?php if ($bays['regular']['capacity'] === null): ?>
              <p><?= te('Capacity unavailable') ?></p>
            <?php else: ?>
              <p><?= te('Regular bays available: {count}', ['count' => $bays['regular']['available']]) ?>
              <?php if ($bays['oku']['capacity'] > 0): ?>
                · <?= te('OKU bays available: {count}', ['count' => $bays['oku']['available']]) ?>
              <?php endif; ?>
              </p>
            <?php endif; ?>
            <div class="find-parking-actions">
              <a class="btn" href="parking-details.php?lot_id=<?= e($lot['id']) ?>"><?= te('Select location') ?></a>
              <?php if ($hasPosition): ?>
                <button class="ghost" type="button" data-show-on-map aria-pressed="false"
                        aria-controls="find-parking-map" hidden><?= te('Show on map') ?></button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>
<script src="assets/js/find-parking.js" defer></script>
<?php foot(); ?>
