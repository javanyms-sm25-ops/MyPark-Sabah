<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/staff-data.php';
$json = defined('MYPARK_JSON_REQUEST') || ($_GET['format'] ?? '') === 'json';
$page = $_GET['page'] ?? 'dashboard';
$pages = ['dashboard' => 'Staff Dashboard', 'fines' => 'Fine Management', 'vehicles' => 'Vehicle Lookup', 'maintenance' => 'Parking Maintenance'];
try {
    $actor = staff_actor();
    if (!is_string($page) || !isset($pages[$page])) throw new RuntimeException('Staff page not found.', 404);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = staff_change($actor, $_POST);
        if ($json) staff_json(['message' => $message, 'data' => staff_snapshot($actor)]);
        flash($message); header('Location: staff.php?page=' . $page); exit;
    }
    $snapshot = staff_snapshot($actor);
    if ($json) staff_json(['data' => $snapshot]);
} catch (RuntimeException $error) {
    $status = in_array($error->getCode(), [400, 401, 403, 404, 409, 422], true) ? $error->getCode() : 500;
    if ($json) staff_json(['error' => $error->getMessage(), 'fields' => $error instanceof StaffValidationException ? $error->fields : (object)[]], $status);
    if ($status === 401) { header('Location: login.php'); exit; }
    http_response_code($status); echo e($error->getMessage()); exit;
}
head($pages[$page]);
?>
<div id="staff-app" data-page="<?= e($page) ?>">
  <div class="staff-page-heading"><div><p class="staff-eyebrow">MY PARK · SABAH</p><h1><?= e($page === 'dashboard' ? 'Staff dashboard' : $pages[$page]) ?></h1>
    <p class="staff-muted"><?= e(['dashboard' => 'An overview of your parking records and outstanding work.', 'fines' => 'Create and manage private parking fine records.', 'vehicles' => 'Find fine and parking records for a vehicle plate.', 'maintenance' => 'Track parking location issues from report to completion.'][$page]) ?></p></div>
    <button type="button" class="staff-button staff-secondary" id="staff-refresh">Refresh</button>
  </div>
  <p class="staff-request-status" id="staff-request-status" role="status" hidden></p>
  <div class="staff-error-banner" id="staff-page-error" role="alert" hidden><span></span><button type="button" class="staff-button staff-secondary" id="staff-retry">Retry</button></div>
  <?php if ($page === 'dashboard'): ?>
    <section class="staff-stats" aria-label="Staff statistics">
      <article><span>Total fines</span><strong data-summary="total_fines"><?= $snapshot['summary']['total_fines'] ?></strong><small>All fine records, including voids</small></article>
      <article><span>Unpaid fines</span><strong data-summary="unpaid_fines"><?= $snapshot['summary']['unpaid_fines'] ?></strong><small>Awaiting payment</small></article>
      <article><span>Outstanding amount</span><strong data-summary="outstanding_cents"><?= staff_money($snapshot['summary']['outstanding_cents']) ?></strong><small>Unpaid records only</small></article>
      <article><span>Open maintenance</span><strong data-summary="open_maintenance"><?= $snapshot['summary']['open_maintenance'] ?></strong><small>Open or in progress</small></article>
    </section>
    <section class="staff-card"><div class="staff-section-heading"><h2>Recent fine records</h2><a href="staff.php?page=fines" class="staff-text-link">Manage fines</a></div><div id="staff-recent"></div></section>
  <?php elseif ($page === 'fines' || $page === 'maintenance'): ?>
    <section class="staff-card"><div class="staff-section-heading"><div><h2><?= $page === 'fines' ? 'Fine records' : 'Maintenance records' ?></h2><span id="staff-record-count" class="staff-muted"></span></div>
      <button type="button" class="staff-button" id="staff-new"><?= $page === 'fines' ? 'New Fine' : 'New maintenance record' ?></button></div>
      <div id="staff-records" data-kind="<?= e($page) ?>"></div>
    </section>
  <?php else: ?>
    <section class="staff-card staff-lookup-card"><h2>Find a vehicle</h2><form id="staff-lookup" method="get">
      <input type="hidden" name="page" value="vehicles"><label for="lookup-plate">Vehicle plate</label>
      <div class="staff-lookup-controls"><input id="lookup-plate" name="plate" maxlength="15" placeholder="Enter vehicle plate" value="<?= e(is_string($_GET['plate'] ?? null) ? $_GET['plate'] : '') ?>" required><button class="staff-button">Search vehicle</button></div>
      <p class="staff-help">Exact plate match; spaces, hyphens and letter case are ignored.</p></form></section>
    <div id="staff-lookup-results" aria-live="polite"><p class="staff-empty">Enter a plate number to view related records.</p></div>
  <?php endif; ?>
  <noscript><p class="staff-error-banner">Enable JavaScript to manage these records. No demo fines or maintenance records have been added.</p></noscript>
</div>
<dialog class="staff-dialog" id="staff-dialog" aria-labelledby="staff-dialog-title">
  <div class="staff-dialog-header"><h2 id="staff-dialog-title"></h2><button type="button" class="staff-button staff-secondary" id="staff-dialog-close">Close</button></div>
  <div id="staff-dialog-body"></div>
</dialog>
<script type="application/json" id="staff-data"><?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php foot(); ?>
