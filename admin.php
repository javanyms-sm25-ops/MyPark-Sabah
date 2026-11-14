<?php
require 'includes.php';
require 'operations-data.php';
$isJson = ($_GET['format'] ?? '') === 'json';
try {
    $actor = ops_actor();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST as $value) if (!is_string($value)) throw new RuntimeException('Invalid request fields.', 400);
        $message = ops_mutate($actor, $_POST);
        if ($isJson) ops_json(['message' => $message, 'data' => ops_snapshot($actor)]);
        flash($message);
        header('Location: admin.php');
        exit;
    }
    $snapshot = ops_snapshot($actor);
    if ($isJson) ops_json(['data' => $snapshot]);
} catch (RuntimeException $error) {
    $status = in_array($error->getCode(), [400, 401, 403, 404, 409, 422], true) ? $error->getCode() : 500;
    if ($isJson) ops_json(['error' => $error->getMessage()], $status);
    if ($status === 401) { header('Location: login.php'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        flash('Operation failed: ' . $error->getMessage());
        header('Location: admin.php');
        exit;
    }
    http_response_code($status);
    head('Operations');
    echo '<p class="error">' . e($error->getMessage()) . '</p>';
    foot();
    exit;
}
$lots = $_SESSION['demo_lots'];
head('Operations');
?>
<link rel="stylesheet" href="assets/operations.css">
<div class="operations-page">
  <div class="ops-page-heading">
    <p class="eyebrow">SABAH PARKING OPERATIONS · <?= e(OPS_ROLES[$actor['role']]) ?></p>
    <h1>Operations dashboard</h1>
    <p class="ops-subtitle">Manage reservations and member access.</p>
  </div>
  <div class="stats">
    <div class="card"><small>Parking locations</small><b data-ops-stat="lots"><?= $snapshot['summary']['lots'] ?></b></div>
    <div class="card"><small>Reservations</small><b data-ops-stat="reservations"><?= $snapshot['summary']['reservations'] ?></b></div>
    <div class="card"><small>Active stays</small><b data-ops-stat="active"><?= $snapshot['summary']['active'] ?></b></div>
    <div class="card"><small>Demo revenue</small><b data-ops-stat="revenue">RM <?= number_format($snapshot['summary']['revenue'], 2) ?></b></div>
  </div>
  <section class="ops-panel" id="reservation-management" aria-labelledby="reservation-heading">
    <div class="ops-panel-heading"><h2 id="reservation-heading">Reservation Management</h2><span class="ops-count" data-total><?= count($snapshot['reservations']) ?> records</span></div>
    <div data-table-host="reservations"><p class="ops-initial">Preparing reservation table…</p></div>
  </section>
  <section class="ops-panel" id="account-management" aria-labelledby="account-heading">
    <div class="ops-panel-heading"><h2 id="account-heading">Account Management</h2><span class="ops-count" data-total><?= count($snapshot['accounts']) ?> records</span></div>
    <p class="ops-caption">Only Super Admin can block or unblock accounts.</p>
    <div data-table-host="accounts"><p class="ops-initial">Preparing account table…</p></div>
  </section>
  <noscript><p class="card">Enable JavaScript to search and manage these tables. Existing records are shown below.</p>
    <div class="ops-table-scroll"><table><thead><tr><th>Member</th><th>Location</th><th>Plate</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($snapshot['reservations'] as $booking): ?><tr><td><?= e($booking['user']) ?></td><td><?= e($booking['lot']) ?></td><td><?= e($booking['plate']) ?></td><td><?= e($booking['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$snapshot['reservations']): ?><tr><td colspan="4">No reservations yet</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="ops-table-scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($snapshot['accounts'] as $account): ?><tr><td><?= e($account['name']) ?></td><td><?= e($account['email']) ?></td><td><?= e($account['role_label']) ?></td><td><?= e($account['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </noscript>
  <h2>Parking locations</h2>
  <div class="lot-grid">
    <?php foreach ($lots as $lot): ?><article class="card"><h3><?= e($lot['name']) ?></h3><p><?= e($lot['city']) ?> · RM <?= number_format($lot['rate'], 2) ?>/hour · <?= (int)$lot['capacity'] ?> spaces</p></article><?php endforeach; ?>
  </div>
  <?php if (in_array($actor['role'], ['admin', 'superadmin'], true)): ?>
    <form method="post" class="card ops-add-location">
      <h2>Add parking location</h2>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="add_lot">
      <label>Name<input name="name" required></label><label>Sabah city<input name="city" required></label><label>Address<input name="address" required></label>
      <label>RM / hour<input type="number" step="0.5" min="0.5" name="rate" required></label><label>Spaces<input type="number" min="1" name="capacity" required></label>
      <button>Add location</button>
    </form>
  <?php endif; ?>
  <section class="card ops-reports"><h2>Reservation reports</h2><div class="stats">
    <?php foreach (['active', 'completed', 'cancelled'] as $status): ?><div><small><?= ucfirst($status) ?></small><b data-ops-stat="<?= $status ?>"><?= $snapshot['summary'][$status] ?></b></div><?php endforeach; ?>
  </div><?php if ($snapshot['can_export']): ?><a class="btn" href="export.php">Export all reservations (CSV)</a><?php endif; ?></section>
</div>
<dialog class="ops-dialog" id="ops-dialog" aria-labelledby="ops-dialog-title"><h2 id="ops-dialog-title"></h2><div data-dialog-body></div><div class="ops-dialog-actions"><button type="button" data-dialog-cancel>Close</button><button type="button" data-dialog-confirm hidden>Confirm</button></div></dialog>
<script type="application/json" id="operations-data"><?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="assets/Js/operations-table.js" defer></script>
<script src="assets/Js/operations.js" defer></script>
<?php foot(); ?>
