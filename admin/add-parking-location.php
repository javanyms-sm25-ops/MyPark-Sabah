<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/dashboard-data.php';

header('Cache-Control: private, no-store');
try {
    $actor = ops_actor();
    if (!in_array($actor['role'], ['admin', 'superadmin'], true)) {
        throw new RuntimeException('Only an administrator can manage parking locations.', 403);
    }
} catch (RuntimeException $error) {
    if ($error->getCode() === 401) {
        header('Location: ../pages/login.php');
        exit;
    }
    http_response_code(403);
    exit(te($error->getMessage()));
}

$page = 'admin/add-parking-location.php';
$canDelete = $actor['role'] === 'superadmin';
$lots = active_parking_locations();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) check_csrf();
$action = $isPost ? ($_POST['action'] ?? 'add_lot') : match (true) {
    isset($_GET['edit']) => 'update_lot',
    isset($_GET['delete']) => 'delete_lot',
    default => 'add_lot',
};
$requestedId = $isPost ? ($_POST['id'] ?? null) : match ($action) {
    'update_lot' => $_GET['edit'],
    'delete_lot' => $_GET['delete'],
    default => null,
};
$id = filter_var($requestedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selected = array_column($lots, null, 'id')[$id] ?? null;
$errorMessage = '';
try {
    if (!in_array($action, ['add_lot', 'update_lot', 'delete_lot'], true)) {
        throw new RuntimeException('Unknown operation.', 400);
    }
    if ($isPost) {
        flash(ops_mutate($actor, $_POST + ['action' => 'add_lot']));
        $next = $action === 'add_lot' ? 'dashboard.php#parking-locations' : 'add-parking-location.php#location-list';
        header('Location: ' . $next, true, 303);
        exit;
    }
    if ($action === 'delete_lot' && !$canDelete) {
        throw new RuntimeException('Only Super Admin can delete parking locations.', 403);
    }
    if ($action !== 'add_lot' && !$selected) {
        throw new RuntimeException('Parking location unavailable.', 404);
    }
} catch (RuntimeException $error) {
    http_response_code($error->getCode() ?: 500);
    $errorMessage = $error->getMessage();
}

$editing = $action === 'update_lot' && $selected !== null;
$confirming = $action === 'delete_lot' && $canDelete && $selected !== null;
$deletePrompt = 'Delete {location}? It will be removed from the map and booking options. Past records will remain available.';
$fields = ['name' => '', 'city' => '', 'address' => '', 'capacity' => '', 'lat' => '', 'lng' => ''];
if ($editing) $fields = array_replace($fields, array_intersect_key($selected, $fields));
if ($isPost && ($action === 'add_lot' || $editing)) {
    $fields = array_replace($fields, array_intersect_key($_POST, $fields));
}
$capacityMessage = t(OPS_PARKING_SPACES_ERROR, ['max' => number_format(OPS_MAX_PARKING_SPACES)]);
$capacityError = $errorMessage === $capacityMessage;
$title = $editing ? 'Edit parking location' : 'Add parking location';
$mapsPath = dirname(__DIR__) . '/config/maps.local.php';
$mapsConfig = is_file($mapsPath) ? require $mapsPath : [];
head($title);
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<div class="operations-page">
  <div class="ops-location-page-links">
    <a class="btn btn-secondary" href="admin/dashboard.php#parking-locations"><?= te('Back to Dashboard') ?></a>
    <a class="btn btn-secondary" href="<?= e($page) ?>#location-list"><?= te('Parking locations') ?></a>
  </div>
  <h1><?= te($title) ?></h1>
  <?php if ($errorMessage !== '' && !$confirming && !$capacityError): ?><p class="error" role="alert"><?= te($errorMessage) ?></p><?php endif; ?>
  <form class="card ops-location-form" id="location-form" method="post" action="<?= e($page) ?>"
        data-maps-key="<?= e(trim((string)($mapsConfig['api_key'] ?? ''))) ?>"
        data-map-id="<?= e(trim((string)($mapsConfig['map_id'] ?? '')) ?: 'DEMO_MAP_ID') ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update_lot' : 'add_lot' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>
    <input type="hidden" name="lat" value="<?= e($fields['lat']) ?>">
    <input type="hidden" name="lng" value="<?= e($fields['lng']) ?>">
    <div class="ops-location-fields">
      <label><?= te('Name') ?><input name="name" value="<?= e($fields['name']) ?>" required></label>
      <label><?= te('City') ?><input name="city" value="<?= e($fields['city']) ?>" required></label>
      <label><?= te('Address') ?><input name="address" value="<?= e($fields['address']) ?>" required></label>
      <div>
        <label for="location-capacity"><?= te('Spaces') ?></label>
        <input id="location-capacity" name="capacity" type="number" min="1" max="<?= OPS_MAX_PARKING_SPACES ?>" step="1"
               value="<?= e($fields['capacity']) ?>" required aria-describedby="location-capacity-feedback"
               <?= $capacityError ? 'aria-invalid="true"' : '' ?>>
        <p class="field-feedback" id="location-capacity-feedback" aria-live="polite"
           data-message="<?= e($capacityMessage) ?>"><?= $capacityError ? e($capacityMessage) : '' ?></p>
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
      <?php if ($editing): ?><a class="btn btn-secondary" href="<?= e($page) ?>#location-list"><?= te('Cancel') ?></a><?php endif; ?>
      <button type="submit"<?= $fields['lat'] === '' || $fields['lng'] === '' ? ' disabled' : '' ?>><?= te($editing ? 'Save changes' : 'Add location') ?></button>
    </div>
  </form>
  <section class="ops-panel ops-location-list" id="location-list" aria-labelledby="location-list-heading">
    <div class="ops-panel-heading">
      <h2 id="location-list-heading"><?= te('Parking locations') ?></h2>
      <span class="ops-count"><?= te('{count} records', ['count' => count($lots)]) ?></span>
    </div>
    <?php
    $cities = array_unique(array_filter(array_column($lots, 'city'), 'strlen'));
    natcasesort($cities);
    $view = ajax_table_view('parking-locations', $lots, [
        'name' => 'Name', 'city' => 'City', 'address' => 'Address', 'capacity' => 'Spaces', 'actions' => 'Actions',
    ], [
        'filter' => ['label' => 'City', 'field' => 'city', 'choices' => ['' => 'All cities'] + array_combine($cities, $cities)],
        'values' => static fn(array $lot) => $lot + ['capacity' => parking_lot_capacity($lot)],
        'search_label' => 'Search parking locations', 'empty' => 'No parking locations yet.', 'numeric' => ['capacity'],
    ]);
    ajax_table_begin($view);
    ?>
    <div class="ops-table-scroll" tabindex="0" role="region" aria-labelledby="location-list-heading">
      <table class="ops-table">
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $lot): ?>
            <tr>
              <td><?= e($lot['name']) ?></td>
              <td><?= e($lot['city'] ?? '') ?></td>
              <td><?= e($lot['address'] ?? '') ?></td>
              <td class="ajax-table-number"><?= e(parking_lot_capacity($lot) ?? '—') ?></td>
              <td data-column="actions">
                <div class="ops-row-actions">
                  <a class="ops-button" href="<?= e($page) ?>?edit=<?= e($lot['id']) ?>#location-form"><?= te('Edit') ?></a>
                  <?php if ($canDelete): ?>
                    <button class="ops-button ops-location-delete-trigger" type="button" aria-haspopup="dialog"
                            data-delete-location="<?= e($lot['id']) ?>"
                            data-delete-prompt="<?= te($deletePrompt, ['location' => $lot['name']]) ?>"><?= te('Delete') ?></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php ajax_table_empty($view); ?>
        </tbody>
      </table>
    </div>
    <?php ajax_table_end($view); ?>
  </section>
</div>
<?php if ($canDelete && $lots): ?>
  <dialog class="ops-dialog ops-location-delete-dialog" id="location-delete"
          aria-labelledby="location-delete-heading" aria-describedby="location-delete-message"
          <?= $confirming ? 'data-reopen' : '' ?>>
    <h2 id="location-delete-heading"><?= te('Delete parking location') ?></h2>
    <p id="location-delete-message"><?= $confirming ? te($deletePrompt, ['location' => $selected['name']]) : '' ?></p>
    <?php if ($confirming && $errorMessage !== ''): ?>
      <p class="error" role="alert" data-location-delete-error><?= te($errorMessage) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e($page) ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="delete_lot">
      <input type="hidden" name="id" value="<?= $confirming ? e($id) : '' ?>">
      <div class="ops-dialog-actions">
        <button type="button" data-location-delete-cancel autofocus><?= te('Cancel') ?></button>
        <button class="ops-location-confirm-button" type="submit"><?= te('Delete location') ?></button>
      </div>
    </form>
  </dialog>
<?php endif; ?>
<script src="assets/js/add-parking-location.js" defer></script>
<?php foot(); ?>
