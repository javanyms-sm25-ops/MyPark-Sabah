<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/receipt-data.php';
require_once __DIR__ . '/includes/staff-data.php';
require_login();

$actor = me();
if (!in_array($actor['role'], ['user', 'admin', 'superadmin'], true)) {
    http_response_code(403);
    exit('The compound payment page is available to members, admins and superadmins.');
}

$canPay = can_use_customer_wallet($actor);
$title = $canPay ? 'Scan & Pay Compound' : 'Check compounds';
$isPaymentRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPaymentRequest) {
    require_customer_wallet();
    check_csrf();
}
staff_store();
if ($canPay) {
    $wallet =& demo_wallet();
    $balanceCents = (int) round($wallet['balance'] * 100);
}
$query = $_GET['q'] ?? '';
$validQuery = is_string($query) && preg_match('/^[A-Z0-9][A-Z0-9 -]{0,23}$/iD', trim($query));
$query = $validQuery ? trim($query) : '';
$lookupKey = staff_plate_key($query);
$error = isset($_GET['q']) && !$validQuery ? 'Enter a compound number or vehicle plate using letters, numbers, spaces or hyphens.' : '';

function compound_matches(array $fine, string $lookupKey): bool {
    return $lookupKey !== '' && ($fine['plate_key'] === $lookupKey || staff_plate_key($fine['code']) === $lookupKey);
}

if ($isPaymentRequest) {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT);
    $fine = MyParkData::$records['staff_fines'][$id] ?? null;

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
            'plate' => $paid['plate'],
        ];
        MyParkData::$records['staff_fines'][$id] = $paid;
        staff_audit('fine', $id, 'paid', $actor, $fine, $paid, 'MyPark demo wallet payment');

        flash([
            'title' => 'Payment confirmed',
            'type' => 'success',
            'message' => t('{amount} paid for compound {compound}.', [
                'amount' => staff_money($paid['paid_amount_cents']),
                'compound' => $paid['code'],
            ]),
        ]);
        header('Location: compound.php?q=' . rawurlencode($query));
        exit;
    }
    http_response_code(409);
}

$results = array_filter(MyParkData::$records['staff_fines'], fn($fine) => compound_matches($fine, $lookupKey));
$compoundReceiptIds = [];
if ($canPay && $results) {
    receipt_sync();
    foreach (MyParkData::$records['demo_receipts'][$actor['id']] ?? [] as $receipt) {
        $details = $receipt['details'];
        if ($receipt['type'] === 'compound' && isset($details['Compound number'], $details['Payment reference'])) {
            $compoundReceiptIds[$details['Compound number']][$details['Payment reference']] = $receipt['id'];
        }
    }
}
$lookupUrl = 'compound.php?q=' . rawurlencode($query);
head($title);
?>
<link rel="stylesheet" href="assets/css/compound.css">
<div class="compound-page">
  <section class="compound-hero" aria-labelledby="compound-title">
    <h1 id="compound-title"><?= te($title) ?></h1>
    <div class="card compound-search">
      <form action="compound.php" method="get" id="compound-lookup" role="search">
        <label for="compound-query"><?= te('Compound number or vehicle plate') ?></label>
        <div class="compound-search-row">
          <input id="compound-query" name="q" value="<?= e($query) ?>" maxlength="24" placeholder="F-000001 or SAB 1234" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
          <button type="submit"><?= te('Search') ?></button>
        </div>
      </form>
      <div class="compound-scan">
        <span><?= te('Or scan the barcode on your compound notice') ?></span>
        <button type="button" id="compound-scan" class="compound-scan-button" hidden><span class="compound-barcode" aria-hidden="true"></span> <?= te('Scan barcode') ?></button>
        <noscript><p>Enter the number above to look up your compound.</p></noscript>
      </div>
      <p id="compound-scan-status" class="hint" role="status"></p>
    </div>
  </section>

  <?php if ($canPay): ?>
    <div class="compound-wallet">
      <span><?= te('Demo wallet balance') ?> <strong><?= staff_money($balanceCents) ?></strong></span>
      <a class="btn btn-secondary" href="wallet.php"><?= te('Top up wallet') ?></a>
      <a class="btn btn-secondary" href="receipts.php?type=compound"><?= te('View compound receipts') ?></a>
    </div>
  <?php endif; ?>
  <?php if ($error): ?><p class="error" role="alert"><?= te($error) ?></p><?php endif; ?>

  <?php if ($query !== ''): ?>
    <section aria-labelledby="compound-results-title">
      <h2 id="compound-results-title"><?= te('Search results') ?></h2>
      <?php if (!$results): ?><p class="card">No compounds found for <?= e($query) ?>. Check the number and try again.</p><?php endif; ?>
      <div class="compound-results">
        <?php foreach ($results as $fine):
            $receiptId = $compoundReceiptIds[$fine['code']][$fine['payment_reference'] ?? ''] ?? null;
        ?>
          <article class="card compound-result">
            <div class="compound-result-heading">
              <h3><?= e($fine['code']) ?></h3>
              <span class="tag <?= e($fine['status']) ?>"><?= te(STAFF_FINE_STATUSES[$fine['status']]) ?></span>
            </div>
            <dl>
              <div><dt><?= te('Vehicle') ?></dt><dd><?= e($fine['plate']) ?></dd></div>
              <div><dt><?= te('Violation') ?></dt><dd><?= e($fine['violation']) ?></dd></div>
              <div><dt><?= te('Issued') ?></dt><dd><?= e(date('d M Y, g:i a', $fine['occurred_ts'])) ?></dd></div>
              <div><dt><?= te('Amount') ?></dt><dd><strong><?= staff_money($fine['amount_cents']) ?></strong></dd></div>
            </dl>

            <?php if ($canPay && $fine['status'] === 'unpaid'): ?>
              <form method="post" action="<?= e($lookupUrl) ?>"
                data-compound-payment="<?= te('Pay {amount} for {compound} from your demo wallet?', ['amount' => staff_money($fine['amount_cents']), 'compound' => $fine['code']]) ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= $fine['id'] ?>">
                <input type="hidden" name="version" value="<?= $fine['version'] ?>">
                <button type="submit" aria-haspopup="dialog"><?= te('Pay {amount} with wallet', ['amount' => staff_money($fine['amount_cents'])]) ?></button>
              </form>
            <?php elseif ($fine['status'] === 'paid' && $receiptId !== null): ?>
              <a class="btn" href="receipts.php?type=compound&amp;id=<?= e($receiptId) ?>"><?= te('View receipt') ?><span class="validation-sr-only"> <?= e($fine['code']) ?></span></a>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php if ($canPay): ?>
  <dialog id="compound-payment" class="compound-payment-dialog" aria-labelledby="compound-payment-title" aria-describedby="compound-payment-message">
    <h2 id="compound-payment-title"><?= te('Confirm compound payment') ?></h2>
    <p id="compound-payment-message"></p>
    <form method="dialog" class="compound-payment-actions">
      <button type="submit" class="ghost" value="cancel" autofocus><?= te('Cancel') ?></button>
      <button type="submit" value="pay"><?= te('Pay with wallet') ?></button>
    </form>
  </dialog>
<?php endif; ?>

<dialog id="compound-camera" class="compound-camera" aria-labelledby="compound-camera-title">
  <h2 id="compound-camera-title"><?= te('Scan compound barcode') ?></h2>
  <p id="compound-camera-status" role="status"><?= te('Requesting camera access…') ?></p>
  <video muted playsinline aria-label="Barcode camera preview"></video>
  <form method="dialog"><button type="submit"><?= te('Close camera') ?></button></form>
</dialog>
<script src="assets/js/compound.js" defer></script>
<?php foot(); ?>
