<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$role = me()['role'];
if ($role === 'superadmin' || $role === 'staff') {
    header('Location: ' . ($role === 'superadmin' ? 'admin/dashboard.php' : 'staff.php'), true, 303);
    exit;
}

require_once __DIR__ . '/includes/parking-availability.php';
require_once __DIR__ . '/includes/oku-verification.php';

$canBook = can_use_customer_wallet(me());
$query = $_GET['q'] ?? '';
$query = is_string($query) && mb_check_encoding($query, 'UTF-8') ? mb_substr(trim($query), 0, 120, 'UTF-8') : '';
$availableOnly = ($_GET['available'] ?? '') === '1';
$okuOnly = ($_GET['oku'] ?? '') === '1';
$favouritesOnly = $canBook && ($_GET['favourites'] ?? '') === '1';
$bayType = $okuOnly ? 'oku' : 'regular';
$searchTerm = trim(mb_convert_kana($query, 'as', 'UTF-8'));

$lots = active_parking_locations();
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

$catalogVersion = parking_availability_catalog_version($lots);
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['_locations'] ?? '') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-MyPark-Parking-Locations: ' . $catalogVersion);
    require __DIR__ . '/includes/find-parking-list.php';
    exit;
}

$showOkuHelp = $canBook && !oku_is_approved(me()['id']);
$mapsConfigPath = __DIR__ . '/config/maps.local.php';
$mapsConfig = is_file($mapsConfigPath) ? require $mapsConfigPath : [];

head('Find parking');
?>
<link rel="stylesheet" href="assets/css/find-parking.css">
<h1><?= te('Find parking') ?></h1>
<div class="find-parking<?= $okuOnly ? ' is-oku-search' : '' ?>" data-find-parking
     data-catalog-version="<?= e($catalogVersion) ?>"
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
            <?php if ($canBook): ?>
              <label data-favourites-filter hidden>
                <input type="checkbox" name="favourites" value="1" aria-controls="find-parking-list" disabled <?= $favouritesOnly ? 'checked' : '' ?>>
                <?= te('Favourites') ?>
              </label>
            <?php endif; ?>
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
      <div class="find-parking-tool-actions">
        <noscript><button type="submit" class="btn btn-secondary" form="find-parking-filters"><?= te('Apply filters') ?></button></noscript>
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
        <div data-parking-empty-default>
          <h3><?= te($lots ? 'No matching parking locations.' : 'No parking locations yet.') ?></h3>
          <p class="find-parking-note" <?= $lots ? '' : 'hidden' ?>><?= te('Try a different search or clear your filters.') ?></p>
        </div>
        <?php if ($canBook): ?>
          <div data-favourites-empty hidden>
            <h3><?= te('No favourites yet') ?></h3>
            <p class="find-parking-note"><?= te('Tap the heart on a parking location to save it in this browser.') ?></p>
            <button class="btn btn-secondary" type="button" data-show-all-parking><?= te('Show all locations') ?></button>
          </div>
          <div data-favourites-no-match hidden>
            <h3><?= te('No favourites match your filters.') ?></h3>
            <p class="find-parking-note"><?= te('Try a different search or clear your filters.') ?></p>
          </div>
        <?php endif; ?>
      </div>
      <?php require __DIR__ . '/includes/find-parking-list.php'; ?>
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
<?php if ($canBook): ?><script src="assets/js/parking-favourites.js" defer></script><?php endif; ?>
<script src="assets/js/find-parking.js" defer></script>
<?php foot(); ?>
