<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$role = me()['role'];
if ($role === 'superadmin' || $role === 'staff') {
    header('Location: ' . ($role === 'superadmin' ? 'admin/dashboard.php' : 'staff.php'), true, 303);
    exit;
}

require_once __DIR__ . '/includes/parking-capacity.php';
require_once __DIR__ . '/includes/oku-verification.php';

$canBook = can_use_customer_wallet(me());
$showOkuHelp = $canBook && !oku_is_approved(me()['id']);
$query = $_GET['q'] ?? '';
$query = is_string($query) && mb_check_encoding($query, 'UTF-8') ? mb_substr(trim($query), 0, 120, 'UTF-8') : '';
$availableOnly = ($_GET['available'] ?? '') === '1';
$okuOnly = ($_GET['oku'] ?? '') === '1';
$bayType = $okuOnly ? 'oku' : 'regular';
$searchTerm = trim(mb_convert_kana($query, 'as', 'UTF-8'));

$lots = MyParkData::$records['demo_lots'];
$occupiedByLot = parking_occupancy_by_lot(MyParkData::$records['demo_bookings']);
$locations = [];
$visibleCount = 0;
foreach ($lots as $lot) {
    $bays = parking_bay_counts($lot, $occupiedByLot[$lot['id']] ?? []);
    $searchText = implode(' ', [$lot['name'], $lot['address'] ?? '', $lot['city'] ?? '']);
    $matches = ($searchTerm === '' || mb_stripos(mb_convert_kana($searchText, 'as', 'UTF-8'), $searchTerm, 0, 'UTF-8') !== false)
        && (!$okuOnly || $bays['oku']['capacity'] > 0)
        && (!$availableOnly || $bays[$bayType]['available'] > 0);
    $visibleCount += (int)$matches;
    $locations[] = ['lot' => $lot, 'bays' => $bays, 'search' => $searchText, 'matches' => $matches];
}

$checkedAt = new DateTimeImmutable();
$mapsConfigPath = __DIR__ . '/config/maps.local.php';
$mapsConfig = is_file($mapsConfigPath) ? require $mapsConfigPath : [];

header('Cache-Control: private, no-store');
head('Find parking');
?>
<link rel="stylesheet" href="assets/css/find-parking.css">
<h1><?= te('Find parking') ?></h1>
<div class="find-parking<?= $okuOnly ? ' is-oku-search' : '' ?>" data-find-parking
     data-maps-key="<?= e(trim($mapsConfig['api_key'] ?? '')) ?>"
     data-map-id="<?= e($mapsConfig['map_id'] ?? 'DEMO_MAP_ID') ?>">
  <section class="find-parking-search card" aria-label="<?= te('Search parking locations') ?>">
    <form id="find-parking-filters" method="get" action="find_parking.php" data-parking-filters>
      <div class="find-parking-filter-row">
        <div class="find-parking-query">
          <label for="find-parking-query"><?= te('Search parking locations') ?></label>
          <input id="find-parking-query" name="q" type="search" value="<?= e($query) ?>"
                 placeholder="<?= te('Search name, city or address') ?>" maxlength="120" autocomplete="off"
                 aria-controls="find-parking-list">
        </div>
        <fieldset class="find-parking-filters">
          <legend><?= te('Filter locations') ?></legend>
          <div class="find-parking-filter-options">
            <label>
              <input type="checkbox" name="available" value="1" aria-controls="find-parking-list"
                     aria-describedby="find-parking-filter-help" <?= $availableOnly ? 'checked' : '' ?>>
              <?= te('Available now') ?>
            </label>
            <label>
              <input type="checkbox" name="oku" value="1" aria-controls="find-parking-list" <?= $okuOnly ? 'checked' : '' ?>>
              <?= te('OKU bays') ?>
            </label>
          </div>
        </fieldset>
      </div>
      <p class="find-parking-note" id="find-parking-filter-help"><?= te('Available now checks regular bays, or OKU bays when the OKU filter is on.') ?></p>
      <?php if ($showOkuHelp): ?>
        <p class="find-parking-note find-parking-oku-help" data-parking-oku-help <?= $okuOnly ? '' : 'hidden' ?>>
          <?= te('Approval is required to book OKU parking.') ?>
          <a href="oku-verification.php"><?= te('OKU Parking Verification') ?></a>
        </p>
      <?php endif; ?>
    </form>
    <div class="find-parking-tools">
      <p class="find-parking-note">
        <?= te('Availability based on active MyPark sessions.') ?>
        <span><?= te('Last checked') ?> <time data-parking-checked-at datetime="<?= e($checkedAt->format(DATE_ATOM)) ?>"><?= e($checkedAt->format('g:i:s A')) ?> MYT</time></span>
        <span data-parking-auto-update hidden><?= te('Updates every 20 seconds while this page is open.') ?></span>
      </p>
      <div class="find-parking-tool-actions">
        <button type="submit" class="btn btn-secondary" form="find-parking-filters" data-refresh-parking><?= te('Apply filters') ?></button>
        <button type="button" class="btn btn-secondary" data-use-location aria-controls="find-parking-map find-parking-list" hidden><?= te('Use my location') ?></button>
      </div>
    </div>
    <p class="find-parking-note" data-parking-refresh-status role="status"></p>
    <p class="find-parking-note" data-location-status role="status"></p>
  </section>

  <section class="find-parking-results" aria-labelledby="find-parking-heading">
    <div class="find-parking-result-heading">
      <div>
        <h2 id="find-parking-heading" tabindex="-1"><?= te('Parking locations') ?></h2>
        <p class="find-parking-note" data-parking-result-count role="status" aria-atomic="true">
          <?= te($visibleCount === 1 ? '{count} location found' : '{count} locations found', ['count' => $visibleCount]) ?>
        </p>
      </div>
      <a class="btn btn-secondary" href="find_parking.php" data-parking-clear <?= $query !== '' || $availableOnly || $okuOnly ? '' : 'hidden' ?>><?= te('Clear filters') ?></a>
    </div>
    <p class="find-parking-note find-parking-map-message" data-map-message role="status"></p>
    <noscript><p class="find-parking-note"><?= te('Enable JavaScript to use the parking map.') ?></p></noscript>

    <div class="find-parking-list-wrap">
      <div class="card find-parking-empty" data-parking-empty <?= $visibleCount ? 'hidden' : '' ?>>
        <h3><?= te($lots ? 'No matching parking locations.' : 'No parking locations yet.') ?></h3>
        <?php if ($lots): ?><p class="find-parking-note"><?= te('Try a different search or clear your filters.') ?></p><?php endif; ?>
      </div>
      <ul class="find-parking-list" id="find-parking-list">
        <?php foreach ($locations as $location): ?>
          <?php
          ['lot' => $lot, 'bays' => $bays, 'search' => $searchText, 'matches' => $matches] = $location;
          $lat = filter_var($lot['lat'] ?? null, FILTER_VALIDATE_FLOAT);
          $lng = filter_var($lot['lng'] ?? null, FILTER_VALIDATE_FLOAT);
          $hasPosition = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180;
          ?>
          <li class="find-parking-location" data-parking-location="<?= e($lot['id']) ?>"
              data-search="<?= e($searchText) ?>" data-regular-available="<?= e($bays['regular']['available']) ?>"
              data-oku-capacity="<?= e($bays['oku']['capacity']) ?>" data-oku-available="<?= e($bays['oku']['available']) ?>"
              <?php if ($hasPosition): ?>data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>"<?php endif; ?>
              <?= $matches ? '' : 'hidden' ?>>
            <h3><?= e($lot['name']) ?></h3>
            <p class="find-parking-address"><?= e(implode(' · ', array_filter([$lot['address'] ?? '', $lot['city'] ?? '']))) ?></p>
            <?php if ($hasPosition): ?><p class="find-parking-distance" data-parking-distance hidden></p><?php endif; ?>
            <dl class="find-parking-availability">
              <div data-parking-capacity-unknown <?= $bays['regular']['capacity'] === null ? '' : 'hidden' ?>>
                <dt><?= te('Parking availability') ?></dt>
                <dd class="find-parking-status"><?= te('Capacity unavailable') ?></dd>
              </div>
              <?php foreach (['regular' => 'Regular bays', 'oku' => 'OKU bays'] as $type => $label):
                  $bay = $bays[$type];
                  $state = match (true) {
                      !$bay['capacity'] => 'none',
                      $bay['available'] === 0 => 'full',
                      default => 'available',
                  };
                  $status = match ($state) {
                      'none' => t('Not offered'),
                      'full' => t('Full'),
                      default => t('{count} available', ['count' => $bay['available']]),
                  };
              ?>
                <div data-parking-bay="<?= e($type) ?>" <?= $bay['capacity'] === null ? 'hidden' : '' ?>>
                  <dt><?= te($label) ?><?php if ($type === 'oku'): ?><span data-parking-oku-rate <?= $bay['capacity'] > 0 ? '' : 'hidden' ?>> · <?= te('Free') ?></span><?php endif; ?></dt>
                  <dd class="find-parking-status" data-bay-status="<?= e($state) ?>"><?= e($status) ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>
            <p class="find-parking-rate"><?= te('Regular parking') ?> · <?= e(parking_price_label()) ?></p>
            <div class="find-parking-actions">
              <?php if ($canBook): ?>
                <a class="btn" href="<?= e('parking-details.php?' . http_build_query(['lot_id' => $lot['id']] + ($okuOnly ? ['bay_type' => 'oku'] : []))) ?>"
                   data-select-parking><?= te('Select location') ?></a>
              <?php endif; ?>
              <?php if ($hasPosition): ?>
                <button class="btn btn-secondary" type="button" data-show-on-map aria-pressed="false"
                        aria-controls="find-parking-map" hidden><?= te('Show on map') ?></button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="find-parking-map-panel card" aria-labelledby="find-parking-map-heading" hidden>
    <div class="find-parking-map-heading">
      <h2 id="find-parking-map-heading"><?= te('Parking map') ?></h2>
      <a class="find-parking-map-back" href="#find-parking-heading"><?= te('Back to results') ?></a>
    </div>
    <div class="find-parking-map" id="find-parking-map"></div>
  </section>
</div>
<script src="assets/js/find-parking.js" defer></script>
<?php foot(); ?>
