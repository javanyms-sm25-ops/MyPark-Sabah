<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/operations-data.php';
$isJson = defined('MYPARK_JSON_REQUEST') || ($_GET['format'] ?? '') === 'json';
$okuReviewAction = in_array($_POST['action'] ?? '', ['oku_decide', 'oku_reviewer_access'], true);
$okuCapacityAction = ($_POST['action'] ?? '') === 'set_oku_capacity';
$leaveReviewAction = ($_POST['action'] ?? '') === 'leave_review';
$redirect = match (true) {
    $okuReviewAction => './?oku_status=' . oku_queue_filter($_POST['oku_status'] ?? null) . '#oku-verification',
    $okuCapacityAction => './#oku-bay-capacity',
    $leaveReviewAction => 'attendance.php',
    default => './',
};
try {
    $actor = ops_actor();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST as $value) if (!is_string($value) || str_contains($value, "\0")) throw new RuntimeException('Invalid request fields.', 400);
        $message = ops_mutate($actor, $_POST);
        if ($isJson) ops_json(['message' => $message, 'data' => ops_snapshot($actor)]);
        flash($message);
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    $snapshot = ops_snapshot($actor);
    if ($isJson) ops_json(['data' => $snapshot]);
} catch (RuntimeException $error) {
    $status = in_array($error->getCode(), [400, 401, 403, 404, 409, 422, 503], true) ? $error->getCode() : 500;
    if ($isJson) ops_json(['error' => $error->getMessage()], $status);
    if ($status === 401) { header('Location: ../pages/login.php'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($status !== 403 || !($okuReviewAction || $leaveReviewAction))) {
        flash($okuReviewAction || $okuCapacityAction || $leaveReviewAction ? $error->getMessage() : 'Operation failed: ' . $error->getMessage());
        header('Location: ' . $redirect, true, 303);
        exit;
    }
    http_response_code($status);
    head('Operations');
    echo '<p class="error">' . te($error->getMessage()) . '</p>';
    foot();
    exit;
}
$lots = $_SESSION['demo_lots'];
$canReviewOku = oku_can_review($actor);
if ($canReviewOku || $actor['role'] === 'admin') header('Cache-Control: private, no-store');
$availability = $snapshot['parking_availability'] ?? null;
if ($availability !== null) {
    $mapsConfigPath = dirname(__DIR__) . '/config/maps.local.php';
    $mapsConfig = is_file($mapsConfigPath) ? require $mapsConfigPath : [];
    $mapsApiKey = trim((string)($mapsConfig['api_key'] ?? ''));
    $mapsId = trim((string)($mapsConfig['map_id'] ?? '')) ?: 'DEMO_MAP_ID';
    $availabilityTime = new DateTimeImmutable($availability['refreshed_at']);
}
head('Operations');
?>
<link rel="stylesheet" href="assets/css/operations.css">
<div class="operations-page">
  <div class="ops-page-heading">
    <div class="ops-page-intro">
      <p class="eyebrow">SABAH PARKING OPERATIONS · <?= te(OPS_ROLES[$actor['role']]) ?></p>
      <h1><?= te('Operations dashboard') ?></h1>
      <p class="ops-subtitle"><?= te('Manage reservations and member access.') ?></p>
      <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
        <a class="btn" href="compound.php"><?= te('Check and pay compounds') ?></a>
      <?php endif; ?>
    </div>
    <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
      <?php $clockTime = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')); ?>
      <div class="ops-clock">
        <span class="ops-clock__label"><?= te('Malaysia time') ?> · MYT</span>
        <time data-ops-clock datetime="<?= e($clockTime->format(DATE_ATOM)) ?>">
          <span class="ops-clock__time"><?= e($clockTime->format('H:i:s')) ?></span>
          <span class="ops-clock__date"><?= e($clockTime->format('j M Y')) ?></span>
        </time>
      </div>
    <?php endif; ?>
  </div>
  <div class="stats">
    <div class="card"><small><?= te('Parking locations') ?></small><b data-ops-stat="lots"><?= $snapshot['summary']['lots'] ?></b></div>
    <div class="card"><small><?= te('Reservations') ?></small><b data-ops-stat="reservations"><?= $snapshot['summary']['reservations'] ?></b></div>
    <div class="card"><small><?= te('Active stays') ?></small><b data-ops-stat="active"><?= $snapshot['summary']['active'] ?></b></div>
    <div class="card"><small><?= te('Demo revenue') ?></small><b data-ops-stat="revenue">RM <?= number_format($snapshot['summary']['revenue'], 2) ?></b></div>
  </div>
  <?php if ($availability !== null): ?>
    <section class="ops-panel" id="parking-availability" aria-labelledby="parking-availability-heading"
      data-maps-key="<?= e($mapsApiKey) ?>" data-maps-id="<?= e($mapsId) ?>">
      <div class="ops-panel-heading ops-availability-heading">
        <h2 id="parking-availability-heading"><?= te('Parking Availability Map') ?></h2>
        <div class="ops-availability-tools">
          <span><?= te('Last refresh') ?> <time data-availability-time datetime="<?= e($availability['refreshed_at']) ?>"><?= e($availabilityTime->format('j M Y, H:i:s')) ?> MYT</time></span>
          <button class="ops-button" type="button" data-availability-refresh hidden><?= te('Refresh availability') ?></button>
        </div>
      </div>
      <p class="ops-caption"><?= te('Select a marker or location to filter the reservations below.') ?></p>
      <p class="ops-caption" data-maps-message role="status" hidden></p>
      <p class="ops-notice" data-availability-error role="alert" hidden></p>
      <div class="ops-availability-layout">
        <div class="ops-map-canvas" data-parking-map role="region" aria-label="<?= te('Map of parking locations') ?>" hidden></div>
        <ul class="ops-availability-list" data-availability-locations aria-label="<?= te('Parking locations') ?>"></ul>
      </div>
      <noscript><p class="ops-caption"><?= te('Enable JavaScript to use the parking availability map.') ?></p></noscript>
    </section>
  <?php endif; ?>
  <section class="ops-panel" id="reservation-management" aria-labelledby="reservation-heading">
    <div class="ops-panel-heading"><h2 id="reservation-heading"><?= te('Reservation Management') ?></h2><span class="ops-count" data-total><?= count($snapshot['reservations']) ?> records</span></div>
    <div data-table-host="reservations"><p class="ops-initial"><?= te('Preparing reservation table…') ?></p></div>
  </section>
  <?php if ($canReviewOku) require dirname(__DIR__) . '/includes/oku-queue.php'; ?>
  <section class="ops-panel" id="account-management" aria-labelledby="account-heading">
    <div class="ops-panel-heading"><h2 id="account-heading"><?= te('Account Management') ?></h2><span class="ops-count" data-total><?= count($snapshot['accounts']) ?> records</span></div>
    <p class="ops-caption"><?= te('Only Super Admin can block or unblock accounts.') ?></p>
    <div data-table-host="accounts"><p class="ops-initial"><?= te('Preparing account table…') ?></p></div>
  </section>
  <noscript><p class="card">Enable JavaScript to search and manage these tables. Existing records are shown below.</p>
    <div class="ops-table-scroll"><table><thead><tr><th><?= te('Member') ?></th><th><?= te('Location') ?></th><th><?= te('Plate') ?></th><th><?= te('Status') ?></th></tr></thead><tbody>
    <?php foreach ($snapshot['reservations'] as $booking): ?><tr><td><?= e($booking['user']) ?></td><td><?= e($booking['lot']) ?></td><td><?= e($booking['plate']) ?></td><td><?= te($booking['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$snapshot['reservations']): ?><tr><td colspan="4"><?= te('No reservations yet') ?></td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="ops-table-scroll"><table><thead><tr><th><?= te('Name') ?></th><th><?= te('Email') ?></th><th><?= te('Role') ?></th><th><?= te('Status') ?></th></tr></thead><tbody>
    <?php foreach ($snapshot['accounts'] as $account): ?><tr><td><?= e($account['name']) ?></td><td><?= e($account['email']) ?></td><td><?= te($account['role_label']) ?></td><td><?= te($account['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </noscript>
  <h2><?= te('Parking locations') ?></h2>
  <div class="lot-grid">
    <?php foreach ($lots as $lot): $capacity = parking_lot_capacity($lot); ?>
      <article class="card"><h3><?= e($lot['name']) ?></h3><p><?= e($lot['city']) ?> · <?= e(parking_price_label()) ?> · <?= $capacity === null ? te('Capacity unavailable') : te('{count} spaces', ['count' => $capacity]) ?></p></article>
    <?php endforeach; ?>
  </div>
  <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
    <form method="post" class="card ops-add-location">
      <h2><?= te('Add parking location') ?></h2>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="add_lot">
      <label><?= te('Name') ?><input name="name" required></label><label><?= te('Sabah city') ?><input name="city" required></label><label><?= te('Address') ?><input name="address" required></label>
      <label><?= te('Spaces') ?><input type="number" min="1" name="capacity" required></label>
      <button><?= te('Add location') ?></button>
    </form>
    <form method="post" action="admin/" class="card ops-add-location" id="oku-bay-capacity">
      <h2><?= te('Set OKU bay allocation') ?></h2>
      <p class="hint ops-oku-capacity-hint"><?= te('OKU bays are reserved within the location’s existing total capacity.') ?></p>
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="action" value="set_oku_capacity">
      <label><?= te('Location') ?>
        <select name="id" required>
          <?php foreach ($lots as $lot): ?>
            <option value="<?= e($lot['id']) ?>"><?= e($lot['name']) ?> · <?= te('OKU bays: {count}', ['count' => parking_bay_counts($lot, [])['oku']['capacity'] ?? '—']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= te('OKU bays') ?><input type="number" name="oku_capacity" min="0" step="1" required></label>
      <button type="submit"><?= te('Save bay allocation') ?></button>
    </form>
  <?php endif; ?>
  <section class="card ops-reports"><h2><?= te('Reservation reports') ?></h2><div class="stats">
    <?php foreach (['active', 'completed', 'cancelled'] as $status): ?><div><small><?= te(ucfirst($status)) ?></small><b data-ops-stat="<?= $status ?>"><?= $snapshot['summary'][$status] ?></b></div><?php endforeach; ?>
  </div><?php if ($snapshot['can_export']): ?><a class="btn" href="api/export.php"><?= te('Export all reservations (CSV)') ?></a><?php endif; ?></section>
</div>
<dialog class="ops-dialog" id="ops-dialog" aria-labelledby="ops-dialog-title"><h2 id="ops-dialog-title"></h2><div data-dialog-body></div><div class="ops-dialog-actions"><button type="button" data-dialog-cancel><?= te('Close') ?></button><button type="button" data-dialog-confirm hidden><?= te('Confirm') ?></button></div></dialog>
<script type="application/json" id="operations-data"><?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php require dirname(__DIR__) . '/includes/date-range-dialog.php'; ?>
<script src="assets/js/operations-table.js" defer></script>
<?php if ($availability !== null): ?><script src="assets/js/parking-availability-map.js" defer></script><?php endif; ?>
<script src="assets/js/operations.js" defer></script>
<?php foot(showChatbot: !$canReviewOku); ?>
