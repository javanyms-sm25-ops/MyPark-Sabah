<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/dashboard-data.php';

header('Cache-Control: private, no-store');
$actor = ops_parking_location_actor();
$page = 'admin/parking-locations.php';
$canDelete = $actor['role'] === 'superadmin';
$lots = active_parking_locations();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) check_csrf();
$id = filter_var($isPost ? ($_POST['id'] ?? null) : ($_GET['delete'] ?? null), FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
$selected = array_column($lots, null, 'id')[$id] ?? null;
$confirming = false;
$errorMessage = '';
if ($isPost || isset($_GET['delete'])) {
    try {
        if ($isPost && ($_POST['action'] ?? '') !== 'delete_lot') {
            throw new RuntimeException('Unknown operation.', 400);
        }
        if (!$canDelete) throw new RuntimeException('Only Super Admin can delete parking locations.', 403);
        if (!$selected) throw new RuntimeException('Parking location unavailable.', 404);
        $confirming = true;
        if ($isPost) {
            flash(ops_mutate($actor, $_POST));
            header('Location: parking-locations.php', true, 303);
            exit;
        }
    } catch (RuntimeException $error) {
        http_response_code($error->getCode() ?: 500);
        $errorMessage = $error->getMessage();
    }
}

$deletePrompt = 'Delete {location}? It will be removed from the map and booking options. Past records will remain available.';
head('Parking locations');
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<div class="operations-page">
  <?php if ($errorMessage !== '' && !$confirming): ?><p class="error" role="alert"><?= te($errorMessage) ?></p><?php endif; ?>
  <section class="ops-panel ops-location-list" id="location-list" aria-labelledby="location-list-heading">
    <div class="ops-panel-heading">
      <h1 id="location-list-heading"><?= te('Parking locations') ?></h1>
      <div class="ops-row-actions">
        <span class="ops-count"><?= te('{count} records', ['count' => count($lots)]) ?></span>
        <a class="btn" href="admin/add-parking-location.php"><?= te('Add parking location') ?></a>
      </div>
    </div>
    <?php
    $cities = array_unique(array_filter(array_column($lots, 'city'), 'strlen'));
    natcasesort($cities);
    $view = ajax_table_view('parking-locations', $lots, [
        'name' => 'Name', 'city' => 'City', 'address' => 'Address', 'capacity' => 'Total spaces',
        'oku_capacity' => 'OKU bays', 'actions' => 'Actions',
    ], [
        'filter' => ['label' => 'City', 'field' => 'city', 'choices' => ['' => 'All cities'] + array_combine($cities, $cities)],
        'values' => static fn(array $lot) => array_replace($lot, [
            'capacity' => parking_lot_capacity($lot),
            'oku_capacity' => parking_bay_counts($lot, [])['oku']['capacity'],
        ]),
        'search_label' => 'Search parking locations', 'empty' => 'No parking locations yet.', 'numeric' => ['capacity', 'oku_capacity'],
    ]);
    ajax_table_begin($view);
    ?>
    <div class="ops-table-scroll" tabindex="0" role="region" aria-labelledby="location-list-heading">
      <table class="ops-table">
        <?php ajax_table_head($view); ?>
        <tbody>
          <?php foreach ($view['rows'] as $lot):
              $photo = parking_location_photo_src($lot);
          ?>
            <tr>
              <td>
                <div class="ops-location-identity">
                  <?php if ($photo !== null): ?>
                    <button type="button" class="ops-location-thumb" data-preview-location-photo="<?= e($lot['name']) ?>"
                            aria-haspopup="dialog" aria-controls="location-photo-preview"
                            aria-label="<?= te('Enlarge photo of {location}', ['location' => $lot['name']]) ?>">
                      <img src="<?= e($photo) ?>" alt="" loading="lazy" decoding="async">
                    </button>
                  <?php else: ?>
                    <span class="ops-location-thumb" role="img" aria-label="<?= te('No photo') ?>">—</span>
                  <?php endif; ?>
                  <span><?= e($lot['name']) ?></span>
                </div>
              </td>
              <td><?= e($lot['city'] ?? '') ?></td>
              <td><?= e($lot['address'] ?? '') ?></td>
              <td class="ajax-table-number"><?= e(parking_lot_capacity($lot) ?? '—') ?></td>
              <td class="ajax-table-number"><?= e(parking_bay_counts($lot, [])['oku']['capacity'] ?? '—') ?></td>
              <td data-column="actions">
                <div class="ops-row-actions">
                  <a class="ops-button" href="admin/add-parking-location.php?edit=<?= e($lot['id']) ?>"><?= te('Edit') ?></a>
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
<?php if ($lots): ?>
  <dialog class="ops-dialog ops-location-preview" id="location-photo-preview" aria-labelledby="location-photo-preview-heading">
    <div class="ops-location-preview-heading">
      <h2 id="location-photo-preview-heading"></h2>
      <form method="dialog">
        <button type="submit" class="btn btn-secondary" autofocus><?= te('Close') ?></button>
      </form>
    </div>
  </dialog>
<?php endif; ?>
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
