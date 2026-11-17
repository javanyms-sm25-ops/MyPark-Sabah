<?php
require 'includes.php';
require_once 'receipt-data.php';
require_login();

header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
demo_wallet();
receipt_sync();

[$firstDay, $today] = receipt_window();
$minDate = $firstDay->format('Y-m-d');
$maxDate = $today->format('Y-m-d');
$startInput = $_GET['start'] ?? $minDate;
$endInput = $_GET['end'] ?? $maxDate;
$start = is_string($startInput) ? receipt_date($startInput) : null;
$end = is_string($endInput) ? receipt_date($endInput) : null;
$category = $_GET['type'] ?? 'all';
$filterError = '';

if (!$start || !$end || $start < $firstDay || $end > $today || $start > $end) {
    $start = $firstDay;
    $end = $today;
    $filterError = 'Choose a start and end date within the past 30 days. The full 30-day range is shown below.';
}
if (!is_string($category) || !array_key_exists($category, ['all' => 'All receipts'] + RECEIPT_TYPES)) {
    $category = 'all';
}
$filters = ['type' => $category, 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
$listUrl = 'receipts.php?' . http_build_query($filters);
$receipts = $_SESSION['demo_receipts'][me()['id']] ?? [];
$showDetails = isset($_GET['id']);
$receipt = null;

if ($showDetails) {
    $id = $_GET['id'];
    if (is_string($id) && preg_match('/\A[0-9A-F]{16}\z/', $id)) $receipt = $receipts[$id] ?? null;
    if (!$receipt) http_response_code(404);
} else {
    $from = $start->getTimestamp();
    $until = $end->modify('+1 day')->getTimestamp();
    $receipts = array_filter($receipts, static fn($item) =>
        $item['at'] >= $from
        && $item['at'] < $until
        && ($category === 'all' || $item['type'] === $category)
    );
    usort($receipts, static fn($a, $b) => $b['at'] <=> $a['at']);
}
$confirmEmail = $receipt && ($_GET['email'] ?? null) === '1';
head($confirmEmail ? 'Email receipt' : 'Digital receipts');
?>
<link rel="stylesheet" href="assets/css/receipts.css">
<div class="receipts-page">
  <?php if (!$showDetails): ?>
    <div class="receipts-hero">
      <h1>Digital receipts</h1>
      <p>View, print or email receipts from the past 30 days.</p>
    </div>

    <nav class="receipts-categories" aria-label="Receipt categories">
      <?php foreach (['all' => 'All receipts'] + RECEIPT_TYPES as $key => $label): ?>
        <a href="receipts.php?<?= e(http_build_query(array_replace($filters, ['type' => $key]))) ?>" <?= $key === $category ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <form class="card receipts-filter" method="get" action="receipts.php" aria-labelledby="receipts-filter-title">
      <h2 id="receipts-filter-title">Filter by date</h2>
      <p id="receipts-date-help">Choose dates from <?= e($firstDay->format('j M Y')) ?> to <?= e($today->format('j M Y')) ?> (Malaysia time).</p>
      <input type="hidden" name="type" value="<?= e($category) ?>">
      <div class="receipts-filter-fields">
        <div>
          <label for="receipt-start">Start date</label>
          <input type="date" id="receipt-start" name="start" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($filters['start']) ?>" aria-describedby="receipts-date-help" required>
        </div>
        <div>
          <label for="receipt-end">End date</label>
          <input type="date" id="receipt-end" name="end" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($filters['end']) ?>" aria-describedby="receipts-date-help" required>
        </div>
        <button type="submit">Apply dates</button>
        <a class="receipts-link" href="receipts.php?type=<?= e($category) ?>">Reset dates</a>
      </div>
      <?php if ($filterError): ?><p class="error" role="alert"><?= e($filterError) ?></p><?php endif; ?>
    </form>

    <section aria-labelledby="receipt-results-title">
      <h2 id="receipt-results-title"><?= count($receipts) ?> <?= count($receipts) === 1 ? 'receipt' : 'receipts' ?> found</h2>
      <?php if (!$receipts): ?>
        <div class="card">
          <h3>No receipts for these dates</h3>
          <p>Try a different date range or choose All receipts to see every transaction type.</p>
        </div>
      <?php endif; ?>

      <ul class="receipt-list">
        <?php foreach ($receipts as $item):
            $itemUrl = $listUrl . '&id=' . $item['id'];
            $summary = $item['details']['Vehicle plate'] ?? $item['details']['Compound number'] ?? '';
        ?>
          <li class="card receipt-row">
            <div>
              <h3><?= e(receipt_title($item)) ?><?= $summary !== '' ? ' · ' . e($summary) : '' ?></h3>
              <p><time datetime="<?= e(date(DATE_ATOM, $item['at'])) ?>"><?= e(receipt_time($item['at'])) ?></time></p>
            </div>
            <p class="receipt-row-amount"><?= e(receipt_money($item['amount_cents'])) ?></p>
            <a class="btn" href="<?= e($itemUrl) ?>">View receipt<span class="receipt-sr-only"> <?= e($item['id']) ?></span></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php elseif (!$receipt): ?>
    <section class="card">
      <h1>Receipt unavailable</h1>
      <p>Only receipts from your account and from the past 30 days are available.</p>
      <a class="btn" href="<?= e($listUrl) ?>">Back to receipts</a>
    </section>
  <?php elseif ($confirmEmail):
      $detailUrl = $listUrl . '&id=' . $receipt['id'];
      $email = me()['email'];
      $mailUrl = 'mailto:' . str_replace('%40', '@', rawurlencode($email))
          . '?subject=' . rawurlencode('MyPark receipt ' . $receipt['id'])
          . '&body=' . rawurlencode(receipt_text($receipt));
  ?>
    <section class="card receipt-email" aria-labelledby="receipt-email-title">
      <h1 id="receipt-email-title">Email this receipt</h1>
      <p>Open an email draft addressed to <strong><?= e($email) ?></strong>?</p>
      <p>The receipt will be in the message. You can review it and select Send in your email app.</p>
      <div class="receipt-actions">
        <a class="btn" href="<?= e($mailUrl) ?>">Open email draft</a>
        <a class="receipts-link" href="<?= e($detailUrl) ?>">Cancel</a>
      </div>
    </section>
  <?php else: ?>
    <a class="receipts-link receipt-back" href="<?= e($listUrl) ?>">Back to receipts</a>
    <article class="card receipt-detail" aria-labelledby="receipt-title">
      <p class="receipt-brand">MyPark Sabah</p>
      <h1 id="receipt-title"><?= e(receipt_title($receipt)) ?></h1>
      <dl class="receipt-details">
        <div><dt>Receipt number</dt><dd><?= e($receipt['id']) ?></dd></div>
        <div><dt>Date (Malaysia time)</dt><dd><time datetime="<?= e(date(DATE_ATOM, $receipt['at'])) ?>"><?= e(receipt_time($receipt['at'])) ?></time></dd></div>
        <?php foreach ($receipt['details'] as $label => $value): ?>
          <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
        <?php endforeach; ?>
        <div class="receipt-total"><dt><?= e(receipt_amount_label($receipt)) ?></dt><dd><?= e(receipt_money($receipt['amount_cents'])) ?></dd></div>
      </dl>
      <p><?= $receipt['type'] === 'parking' ? 'Demo booking. No payment was collected.' : 'Demo transaction.' ?></p>
      <div class="receipt-actions">
        <button type="button" onclick="window.print()">Print or save as PDF</button>
        <a class="btn" href="<?= e($listUrl . '&id=' . $receipt['id'] . '&email=1') ?>">Email receipt</a>
      </div>
    </article>
  <?php endif; ?>
</div>
<?php foot(false); ?>
