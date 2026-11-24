<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/operations-data.php';

header('Cache-Control: private, no-store');
try {
    $actor = ops_actor();
    if (!in_array($actor['role'], ['admin', 'superadmin'], true)) {
        throw new RuntimeException('Only an administrator can add locations.', 403);
    }
} catch (RuntimeException $error) {
    if ($error->getCode() === 401) {
        header('Location: ../pages/login.php');
        exit;
    }
    http_response_code(403);
    exit(te($error->getMessage()));
}

$fields = ['name' => '', 'city' => '', 'address' => '', 'capacity' => '', 'lat' => '', 'lng' => ''];
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $fields = array_replace($fields, array_intersect_key($_POST, $fields));
    try {
        flash(ops_mutate($actor, $fields + ['action' => 'add_lot', 'csrf' => $_POST['csrf']]));
        header('Location: ./#parking-locations', true, 303);
        exit;
    } catch (RuntimeException $error) {
        http_response_code($error->getCode() ?: 500);
        $errorMessage = $error->getMessage();
    }
}

$mapsPath = dirname(__DIR__) . '/config/maps.local.php';
$mapsConfig = is_file($mapsPath) ? require $mapsPath : [];
head('Add parking location');
?>
<link rel="stylesheet" href="assets/css/operations.css">
<div class="operations-page">
  <a href="admin/#parking-locations"><?= te('Back to Operations') ?></a>
  <h1><?= te('Add parking location') ?></h1>
  <?php if ($errorMessage !== ''): ?><p class="error" role="alert"><?= te($errorMessage) ?></p><?php endif; ?>
  <form class="card ops-location-form" method="post" action="admin/add-parking-location.php"
        data-maps-key="<?= e(trim((string)($mapsConfig['api_key'] ?? ''))) ?>"
        data-map-id="<?= e(trim((string)($mapsConfig['map_id'] ?? '')) ?: 'DEMO_MAP_ID') ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="ops-location-fields">
      <label><?= te('Name') ?><input name="name" value="<?= e($fields['name']) ?>" required></label>
      <label><?= te('Sabah city') ?><input name="city" value="<?= e($fields['city']) ?>" required></label>
      <label><?= te('Address') ?><input name="address" value="<?= e($fields['address']) ?>" required></label>
      <label><?= te('Spaces') ?><input name="capacity" type="number" min="1" value="<?= e($fields['capacity']) ?>" required></label>
    </div>
    <section aria-labelledby="location-position-heading">
      <h2 id="location-position-heading"><?= te('Map position') ?></h2>
      <p class="hint" data-location-map-status role="status"><?= te('Enter the latitude and longitude to set the map position.') ?></p>
      <div class="ops-location-map" data-location-map role="region" aria-label="<?= te('Parking map') ?>" hidden></div>
      <div class="ops-location-coordinates">
        <label><?= te('Latitude') ?><input name="lat" type="number" step="any" min="-90" max="90" value="<?= e($fields['lat']) ?>" required></label>
        <label><?= te('Longitude') ?><input name="lng" type="number" step="any" min="-180" max="180" value="<?= e($fields['lng']) ?>" required></label>
      </div>
    </section>
    <div class="ops-location-submit"><button type="submit"><?= te('Add location') ?></button></div>
  </form>
</div>
<script src="assets/js/add-parking-location.js" defer></script>
<?php foot(); ?>
