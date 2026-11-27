<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/dashboard-data.php';

header('Cache-Control: private, no-store');
$actor = ops_parking_location_actor();
$page = 'admin/add-parking-location.php';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) check_csrf();
if ($isPost && ($_POST['action'] ?? '') === 'delete_lot') {
    header('Location: parking-locations.php', true, 307);
    exit;
}
if (!$isPost && isset($_GET['delete']) && !isset($_GET['edit'])) {
    header('Location: parking-locations.php?' . http_build_query(['delete' => $_GET['delete']]), true, 302);
    exit;
}

$action = $isPost ? ($_POST['action'] ?? 'add_lot') : (isset($_GET['edit']) ? 'update_lot' : 'add_lot');
$editing = $action === 'update_lot';
$id = filter_var($isPost ? ($_POST['id'] ?? null) : ($_GET['edit'] ?? null), FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
$selected = $editing ? (array_column(active_parking_locations(), null, 'id')[$id] ?? null) : null;
$showForm = $action === 'add_lot' || $selected !== null;
$errorMessage = '';
try {
    if (!$showForm) {
        if ($editing) throw new RuntimeException('Parking location unavailable.', 404);
        throw new RuntimeException('Unknown operation.', 400);
    }
    if ($isPost) {
        flash(ops_mutate($actor, $_POST + ['action' => 'add_lot'], $_FILES['photo'] ?? null));
        header('Location: parking-locations.php', true, 303);
        exit;
    }
} catch (RuntimeException $error) {
    http_response_code($error->getCode() ?: 500);
    $errorMessage = $error->getMessage();
}

$fields = ['name' => '', 'city' => '', 'address' => '', 'capacity' => '', 'oku_capacity' => 0, 'lat' => '', 'lng' => ''];
if ($selected !== null) $fields = array_replace($fields, array_intersect_key($selected, $fields));
if ($isPost) $fields = array_replace($fields, array_intersect_key($_POST, $fields));
$capacityMessage = t(OPS_PARKING_SPACES_ERROR, ['max' => number_format(OPS_MAX_PARKING_SPACES)]);
$capacityError = $errorMessage === $capacityMessage;
$okuCapacityError = $errorMessage === OPS_OKU_CAPACITY_ERROR;
$title = $editing ? 'Edit parking location' : 'Add parking location';
$photo = $selected === null ? null : parking_location_photo_src($selected);
$mapsPath = dirname(__DIR__) . '/config/maps.local.php';
$mapsConfig = is_file($mapsPath) ? require $mapsPath : [];
head($title);
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/profile.css">
<div class="operations-page">
  <div class="ops-location-page-links">
    <a class="btn btn-secondary" href="admin/parking-locations.php"><?= te('Back to parking locations') ?></a>
  </div>
  <h1><?= te($title) ?></h1>
  <?php if ($errorMessage !== '' && !$capacityError && !$okuCapacityError): ?><p class="error" role="alert"><?= te($errorMessage) ?></p><?php endif; ?>
  <?php if ($showForm): ?>
    <form class="card ops-location-form" id="location-form" method="post" action="<?= e($page) ?>" enctype="multipart/form-data"
          data-maps-key="<?= e(trim((string)($mapsConfig['api_key'] ?? ''))) ?>"
          data-map-id="<?= e(trim((string)($mapsConfig['map_id'] ?? '')) ?: 'DEMO_MAP_ID') ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="<?= $editing ? 'update_lot' : 'add_lot' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>
      <input type="hidden" name="lat" value="<?= e($fields['lat']) ?>">
      <input type="hidden" name="lng" value="<?= e($fields['lng']) ?>">
      <section class="profile-photo-editor ops-location-photo" data-photo-drop aria-labelledby="location-photo-heading">
        <div class="profile-avatar-frame">
          <img class="profile-avatar" data-photo-preview alt="<?= te('Parking location photo') ?>"
            <?php if ($photo !== null): ?>src="<?= e($photo) ?>"<?php else: ?>hidden<?php endif; ?>>
          <span class="profile-avatar-initial" data-photo-initial aria-hidden="true" <?= $photo !== null ? 'hidden' : '' ?>>P</span>
        </div>
        <div class="profile-photo-content">
          <h2 id="location-photo-heading"><?= te('Parking location photo') ?></h2>
          <div class="profile-photo-controls">
            <input class="validation-sr-only" id="location-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
              aria-describedby="location-photo-hint location-photo-feedback">
            <label for="location-photo" class="btn btn-secondary profile-photo-action"><?= te($photo === null ? 'Choose photo' : 'Change photo') ?></label>
          </div>
          <p class="profile-photo-hint" id="location-photo-hint"><?= te('JPG, PNG or WebP, up to 2 MB.') ?>
            <span class="profile-drop-hint"><?= te('You can also drag and drop a photo here.') ?></span>
          </p>
          <p class="profile-photo-filename" data-photo-filename role="status"></p>
          <p class="field-feedback" id="location-photo-feedback" aria-live="polite" aria-atomic="true"></p>
        </div>
      </section>
      <div class="ops-location-fields">
        <label><?= te('Name') ?><input name="name" value="<?= e($fields['name']) ?>" required></label>
        <label><?= te('City') ?><input name="city" value="<?= e($fields['city']) ?>" required></label>
        <label><?= te('Address') ?><input name="address" value="<?= e($fields['address']) ?>" required></label>
        <div>
          <label for="location-capacity"><?= te('Total spaces') ?></label>
          <input id="location-capacity" name="capacity" type="number" min="1" max="<?= OPS_MAX_PARKING_SPACES ?>" step="1"
                 value="<?= e($fields['capacity']) ?>" required aria-describedby="location-capacity-feedback"
                 <?= $capacityError ? 'aria-invalid="true"' : '' ?>>
          <p class="field-feedback" id="location-capacity-feedback" aria-live="polite"
             data-message="<?= e($capacityMessage) ?>"><?= $capacityError ? e($capacityMessage) : '' ?></p>
        </div>
        <div>
          <label for="location-oku-capacity"><?= te('OKU bays') ?></label>
          <input id="location-oku-capacity" name="oku_capacity" type="number" min="0" max="<?= OPS_MAX_PARKING_SPACES ?>" step="1"
                 value="<?= e($fields['oku_capacity']) ?>" required
                 aria-describedby="location-oku-capacity-hint location-oku-capacity-feedback"
                 <?= $okuCapacityError ? 'aria-invalid="true"' : '' ?>>
          <p class="hint" id="location-oku-capacity-hint"><?= te('OKU bays are included in the total number of spaces.') ?></p>
          <p class="field-feedback" id="location-oku-capacity-feedback" aria-live="polite"
             data-message="<?= te(OPS_OKU_CAPACITY_ERROR) ?>"><?= $okuCapacityError ? te(OPS_OKU_CAPACITY_ERROR) : '' ?></p>
        </div>
      </div>
      <section aria-labelledby="location-position-heading">
        <h2 id="location-position-heading"><?= te('Map position') ?></h2>
        <div class="ops-place-search" data-place-search hidden>
          <div data-place-search-input></div>
          <p class="hint" data-place-search-status role="status"></p>
        </div>
        <p class="hint" data-location-map-status role="status"><?= te('Choose a location on the map.') ?></p>
        <div class="ops-location-map" data-location-map role="region" aria-label="<?= te('Parking map') ?>" hidden></div>
      </section>
      <div class="ops-location-submit">
        <?php if ($editing): ?><a class="btn btn-secondary" href="admin/parking-locations.php"><?= te('Cancel') ?></a><?php endif; ?>
        <button type="submit"<?= $fields['lat'] === '' || $fields['lng'] === '' ? ' disabled' : '' ?>><?= te($editing ? 'Save changes' : 'Add location') ?></button>
      </div>
    </form>
  <?php endif; ?>
</div>
<script src="assets/js/add-parking-location.js" defer></script>
<?php foot(); ?>
