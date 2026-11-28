<?php
const MYPARK_ADMIN_PAGE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/dashboard-data.php';
$isJson = defined('MYPARK_JSON_REQUEST') || ($_GET['format'] ?? '') === 'json';
$okuReviewAction = in_array($_POST['action'] ?? '', ['oku_decide', 'oku_reviewer_access'], true);
$okuCapacityAction = ($_POST['action'] ?? '') === 'set_oku_capacity';
$leaveReviewAction = ($_POST['action'] ?? '') === 'leave_review';
$redirect = match (true) {
    $okuReviewAction => 'dashboard.php?oku_status=' . oku_queue_filter($_POST['oku_status'] ?? null) . '#oku-verification',
    $okuCapacityAction => 'parking-locations.php',
    $leaveReviewAction => 'attendance.php',
    default => 'dashboard.php',
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
    if ($isJson && isset($_GET['table'])) {
        $table = $_GET['table'];
        if (!in_array($table, ['accounts', 'reservations'], true)) throw new RuntimeException('Table unavailable.', 404);
        $query = ajax_table_query();
        $query += ['sort' => 'id', 'dir' => $table === 'accounts' ? 'asc' : 'desc'];
        $rows = ops_filter_sort($snapshot[$table], $table, $query);
        ops_json(['table' => ajax_table_page($rows, $query['row_page'] ?? null, $query['size'] ?? null)]);
    }
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
    head('Dashboard');
    echo '<p class="error">' . te($error->getMessage()) . '</p>';
    foot();
    exit;
}
$canReviewOku = oku_can_review($actor);
if ($canReviewOku || $actor['role'] === 'admin') header('Cache-Control: private, no-store');
$availability = $snapshot['parking_availability'] ?? null;
$isSuperAdmin = $actor['role'] === 'superadmin';
$mapsApiKey = $mapsId = '';
if ($availability !== null) {
    $mapsConfigPath = dirname(__DIR__) . '/config/maps.local.php';
    $mapsConfig = is_file($mapsConfigPath) ? require $mapsConfigPath : [];
    $mapsApiKey = trim((string)($mapsConfig['api_key'] ?? ''));
    $mapsId = trim((string)($mapsConfig['map_id'] ?? '')) ?: 'DEMO_MAP_ID';
}
$availabilityTime = $availability === null ? null : new DateTimeImmutable($availability['refreshed_at']);
head('Dashboard');
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<div class="operations-page">
  <div class="ops-page-heading">
    <div class="ops-page-intro">
      <p class="eyebrow"><?= te('SABAH PARKING') ?> · <?= te(OPS_ROLES[$actor['role']]) ?></p>
      <h1><?= te('Dashboard') ?></h1>
      <p class="ops-subtitle"><?= te('Manage reservations and member access.') ?></p>
      <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
        <a class="btn" href="compound.php"><?= te('Compound overview') ?></a>
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
    <div class="card"><small><?= te('Parking revenue') ?></small><b data-ops-stat="revenue">RM <?= number_format($snapshot['summary']['revenue'], 2) ?></b></div>
  </div>
  <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
    <section class="ops-panel" aria-labelledby="attendance-heading">
      <div class="ops-panel-heading">
        <div><h2 id="attendance-heading"><?= te('Employee Attendance') ?></h2><p><?= te('Review shifts, attendance and requests for your permitted locations.') ?></p></div>
        <a class="btn" href="admin/employee-attendance.php"><?= te('Open attendance management') ?></a>
      </div>
    </section>
  <?php endif; ?>
  <?php if ($availability !== null): ?>
    <section class="ops-panel" id="parking-availability" aria-labelledby="parking-availability-heading"
      data-map-enabled="true" data-maps-key="<?= e($mapsApiKey) ?>" data-maps-id="<?= e($mapsId) ?>">
      <div class="ops-panel-heading ops-availability-heading">
        <h2 id="parking-availability-heading"><?= te('Parking Availability Map') ?></h2>
        <div class="ops-availability-tools">
          <span><?= te('Last refresh') ?> <time data-availability-time datetime="<?= e($availability['refreshed_at']) ?>"><?= e($availabilityTime->format('j M Y, H:i:s')) ?> MYT</time></span>
        </div>
      </div>
      <p class="ops-caption">
        <?= te('Select a marker or location to filter the reservations below.') ?>
        <?= te($isSuperAdmin ? 'Updates every minute while this page is open.' : 'Updates every 20 seconds while this page is open.') ?>
      </p>
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
  <noscript>
    <p class="card"><?= te('Enable JavaScript to manage these records.') ?></p>
    <?php foreach ([
        'reservations' => ['Reservation Management', ['user' => 'Member', 'lot' => 'Location', 'plate' => 'Plate', 'status' => 'Status'], 'No reservations yet'],
        'accounts' => ['Account Management', ['name' => 'Name', 'email' => 'Email', 'role_label' => 'Role', 'status' => 'Status'], 'No accounts yet'],
    ] as $kind => [$heading, $columns, $empty]): ?>
      <section class="ops-panel">
        <div class="ops-panel-heading"><h2><?= te($heading) ?></h2></div>
        <?php $view = ajax_table_view($kind, $snapshot[$kind], $columns, ['empty' => $empty]); ?>
        <?php ajax_table_begin($view); ?>
        <div class="ops-table-scroll">
          <table class="ops-table" aria-label="<?= te($heading) ?>">
            <?php ajax_table_head($view); ?>
            <tbody>
              <?php foreach ($view['rows'] as $row): ?>
                <tr><?php foreach (array_keys($columns) as $field): ?>
                  <td><?= in_array($field, ['role_label', 'status'], true) ? te($row[$field]) : e($row[$field]) ?></td>
                <?php endforeach; ?></tr>
              <?php endforeach; ?>
              <?php ajax_table_empty($view); ?>
            </tbody>
          </table>
        </div>
        <?php ajax_table_end($view); ?>
      </section>
    <?php endforeach; ?>
  </noscript>
</div>
<dialog class="ops-dialog" id="ops-dialog" aria-labelledby="ops-dialog-title"><h2 id="ops-dialog-title"></h2><div data-dialog-body></div><div class="ops-dialog-actions"><button type="button" data-dialog-cancel><?= te('Close') ?></button><button type="button" data-dialog-confirm hidden><?= te('Confirm') ?></button></div></dialog>
<script type="application/json" id="operations-data" data-fast-availability="<?= $actor['role'] === 'admin' ? 'true' : 'false' ?>"><?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php require dirname(__DIR__) . '/includes/date-range-dialog.php'; ?>
<script src="assets/js/dashboard-table.js" defer></script>
<?php if ($availability !== null): ?><script src="assets/js/parking-availability-map.js" defer></script><?php endif; ?>
<script src="assets/js/dashboard.js" defer></script>
<?php foot(showChatbot: !$canReviewOku); ?>
