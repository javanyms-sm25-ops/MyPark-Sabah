<?php
require 'includes.php';
require 'staff-data.php';
require_login();

$actor = me();
if (!in_array($actor['role'], ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    exit('The compound payment page is available to members, admins and superadmins.');
}

staff_store();
$wallet =& demo_wallet();
$query = $_GET['q'] ?? '';
$validQuery = is_string($query) && preg_match('/^[A-Z0-9][A-Z0-9 -]{0,23}$/iD', trim($query));
$query = $validQuery ? trim($query) : '';
$lookupKey = staff_plate_key($query);
$error = isset($_GET['q']) && !$validQuery ? 'Enter a compound number or vehicle plate using letters, numbers, spaces or hyphens.' : '';

function compound_matches(array $fine, string $lookupKey): bool {
    return $lookupKey !== '' && ($fine['plate_key'] === $lookupKey || staff_plate_key($fine['code']) === $lookupKey);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT);
    $fine = $_SESSION['staff_fines'][$id] ?? null;
    $balanceCents = (int) round($wallet['balance'] * 100);

    if (!$fine || !compound_matches($fine, $lookupKey)) {
        $error = 'Compound not found. Search for it again.';
    } elseif ($fine['status'] !== 'unpaid') {
        $error = 'This compound is ' . $fine['status'] . ' and cannot be paid.';
    } elseif ($fine['version'] !== $version) {
        $error = 'This compound changed. Review the details before paying.';
    } elseif ($fine['amount_cents'] > $balanceCents) {
        $error = 'Your wallet balance is too low. Top up your wallet and try again.';
    } else {
        $paid = $fine;
        $paid['status'] = 'paid';
        $paid['paid_at'] = $paid['updated_at'] = staff_now();
        $paid['paid_amount_cents'] = $fine['amount_cents'];
        $paid['payment_reference'] = 'MP-' . strtoupper(bin2hex(random_bytes(8)));
        $paid['version']++;

        $wallet['balance'] = ($balanceCents - $paid['paid_amount_cents']) / 100;
        $wallet['transactions'][] = [
            'type' => 'compound',
            'amount' => $paid['paid_amount_cents'] / 100,
            'created_at' => date('Y-m-d H:i'),
            'status' => 'paid',
            'compound' => $paid['code'],
            'reference' => $paid['payment_reference'],
        ];
        $_SESSION['staff_fines'][$id] = $paid;
        staff_audit('fine', $id, 'paid', $actor, $fine, $paid, 'MyPark demo wallet payment');

        flash('Compound ' . $paid['code'] . ' paid. Receipt: ' . $paid['payment_reference'] . '.');
        header('Location: compound.php?q=' . rawurlencode($query));
        exit;
    }
    http_response_code(409);
}

$results = array_filter($_SESSION['staff_fines'], fn($fine) => compound_matches($fine, $lookupKey));
$lookupUrl = 'compound.php?q=' . rawurlencode($query);
head('Scan & Pay Compound');
?>
<link rel="stylesheet" href="assets/css/compound.css">
<div class="compound-page">
  <section class="compound-hero" aria-labelledby="compound-title">
    <h1 id="compound-title">Scan &amp; Pay Compound</h1>
    <div class="card compound-search">
      <form action="compound.php" method="get" id="compound-lookup" role="search">
        <label for="compound-query">Compound number or vehicle plate</label>
        <div class="compound-search-row">
          <input id="compound-query" name="q" value="<?= e($query) ?>" maxlength="24" placeholder="F-000001 or SAB 1234" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
          <button type="submit">Search</button>
        </div>
      </form>
      <div class="compound-scan">
        <span>Or scan the barcode on your compound notice</span>
        <button type="button" id="compound-scan" class="compound-scan-button" hidden><span class="compound-barcode" aria-hidden="true"></span> Scan barcode</button>
        <noscript><p>Enter the number above to look up your compound.</p></noscript>
      </div>
      <p id="compound-scan-status" class="hint" role="status"></p>
    </div>
  </section>

  <div class="compound-wallet">
    <span>Demo wallet balance <strong><?= staff_money((int) round($wallet['balance'] * 100)) ?></strong></span>
    <a href="wallet.php">Top up wallet</a>
  </div>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if ($query !== ''): ?>
    <section aria-labelledby="compound-results-title">
      <h2 id="compound-results-title">Search results</h2>
      <?php if (!$results): ?><p class="card">No compounds found for <?= e($query) ?>. Check the number and try again.</p><?php endif; ?>
      <div class="compound-results">
        <?php foreach ($results as $fine): ?>
          <article class="card compound-result">
            <div class="compound-result-heading">
              <h3><?= e($fine['code']) ?></h3>
              <span class="tag <?= e($fine['status']) ?>"><?= e(STAFF_FINE_STATUSES[$fine['status']]) ?></span>
            </div>
            <dl>
              <div><dt>Vehicle</dt><dd><?= e($fine['plate']) ?></dd></div>
              <div><dt>Violation</dt><dd><?= e($fine['violation']) ?></dd></div>
              <div><dt>Issued</dt><dd><?= e(date('d M Y, g:i a', $fine['occurred_ts'])) ?></dd></div>
              <div><dt>Amount</dt><dd><strong><?= staff_money($fine['amount_cents']) ?></strong></dd></div>
            </dl>

            <?php if ($fine['status'] === 'unpaid'): ?>
              <form method="post" action="<?= e($lookupUrl) ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= $fine['id'] ?>">
                <input type="hidden" name="version" value="<?= $fine['version'] ?>">
                <button type="submit" data-confirm="<?= e('Pay ' . staff_money($fine['amount_cents']) . ' for ' . $fine['code'] . ' from your demo wallet?') ?>">Pay <?= staff_money($fine['amount_cents']) ?> with wallet</button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<dialog id="compound-camera" class="compound-camera" aria-labelledby="compound-camera-title">
  <h2 id="compound-camera-title">Scan compound barcode</h2>
  <p id="compound-camera-status" role="status">Requesting camera access…</p>
  <video muted playsinline aria-label="Barcode camera preview"></video>
  <form method="dialog"><button type="submit">Close camera</button></form>
</dialog>
<script src="assets/js/compound.js" defer></script>
<?php foot(); ?>
