<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/receipt-data.php';
require_login();

header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
[$firstDay, $today] = receipt_window();
$minDate = $firstDay->format('Y-m-d');
$maxDate = $today->format('Y-m-d');
$startInput = $_GET['start'] ?? $minDate;
$endInput = $_GET['end'] ?? $maxDate;
$datesApplied = isset($_GET['start']) || isset($_GET['end']);
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
$dateQuery = $datesApplied ? ['start' => $filters['start'], 'end' => $filters['end']] : [];
$listUrl = 'receipts.php?' . http_build_query(['type' => $category] + $dateQuery);
$userId = me()['id'];
$showDetails = isset($_GET['id']);
$showParking = !$showDetails && $category === 'parking';
$showParkingActions = !$showDetails && in_array($category, ['all', 'parking'], true);
$parkingUrl = $listUrl . ($showParking ? '#parking-sessions' : '#receipt-results-title');
$bayError = '';
$from = $start->getTimestamp();
$until = $end->modify('+1 day')->getTimestamp();
if ($showParkingActions) require_once __DIR__ . '/includes/parking-booking.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if (!$showParkingActions || !in_array($action, ['cancel', 'change_parking_bay'], true)) {
        http_response_code(400);
        exit(te('Invalid request.'));
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if ($id === false) throw new RuntimeException('Booking not found.', 404);
        if ($action === 'cancel') {
            cancel_booking($id, $userId);
            if (me()['role'] === 'staff') flash('Booking cancelled.');
        } else {
            change_parking_bay(me(), $id, $_POST['bay_type'] ?? '');
            flash('Parking bay type updated.');
        }
        header('Location: ' . $parkingUrl, true, 303);
        exit;
    } catch (RuntimeException $error) {
        $bayError = $error->getMessage();
        http_response_code($error->getCode());
    }
}

demo_wallet();
receipt_sync();
$receipts = $_SESSION['demo_receipts'][$userId] ?? [];
$receipt = null;

if ($showDetails) {
    $id = $_GET['id'];
    if (is_string($id) && preg_match('/\A[0-9A-F]{16}\z/', $id)) $receipt = $receipts[$id] ?? null;
    if (!$receipt) http_response_code(404);
} else {
    $receipts = array_filter($receipts, static fn($item) =>
        $item['at'] >= $from
        && $item['at'] < $until
        && ($category === 'all' || $item['type'] === $category)
    );
    usort($receipts, static fn($a, $b) => $b['at'] <=> $a['at']);
}
$parkingRows = $showParking ? receipt_parking_rows($userId, $receipts, $datesApplied ? $from : null, $until) : [];
$bookingsByReceipt = $showParkingActions && !$showParking
    ? array_column(array_filter($_SESSION['demo_bookings'], static fn($booking) =>
        $booking['user_id'] === $userId && isset($booking['receipt_id'])), null, 'receipt_id')
    : [];
head('Digital receipts');
?>
<link rel="stylesheet" href="assets/css/receipts.css">
<div class="receipts-page<?= $showParking ? ' receipts-page--parking' : '' ?>">
  <?php if (!$showDetails): ?>
    <div class="receipts-hero">
      <h1><?= te('Digital receipts') ?></h1>
      <p><?= te('View or print receipts from the past 30 days.') ?></p>
    </div>

    <nav class="receipts-categories" aria-label="Receipt categories">
      <?php foreach (['all' => 'All receipts'] + RECEIPT_TYPES as $key => $label): ?>
        <a href="receipts.php?<?= e(http_build_query(['type' => $key] + $dateQuery)) ?>" <?= $key === $category ? 'aria-current="page"' : '' ?>><?= te($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <form class="card receipts-filter" method="get" action="receipts.php" aria-labelledby="receipts-filter-title">
      <h2 id="receipts-filter-title"><?= te('Filter by date') ?></h2>
      <p id="receipts-date-help">Choose dates from <?= e($firstDay->format('j M Y')) ?> to <?= e($today->format('j M Y')) ?> (Malaysia time).</p>
      <input type="hidden" name="type" value="<?= e($category) ?>">
      <div class="receipts-filter-fields">
        <div>
          <label for="receipt-start"><?= te('Start date') ?></label>
          <input type="date" id="receipt-start" name="start" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($filters['start']) ?>" aria-describedby="receipts-date-help" required>
        </div>
        <div>
          <label for="receipt-end"><?= te('End date') ?></label>
          <input type="date" id="receipt-end" name="end" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" value="<?= e($filters['end']) ?>" aria-describedby="receipts-date-help" required>
        </div>
        <button type="submit"><?= te('Apply dates') ?></button>
        <a class="receipts-link" href="receipts.php?type=<?= e($category) ?>"><?= te('Reset dates') ?></a>
      </div>
      <?php if ($filterError): ?><p class="error" role="alert"><?= te($filterError) ?></p><?php endif; ?>
    </form>

    <?php if ($showParking): ?>
      <?php require __DIR__ . '/includes/parking-receipts.php'; ?>
    <?php else: ?>
      <section aria-labelledby="receipt-results-title">
        <h2 id="receipt-results-title"><?= count($receipts) ?> <?= count($receipts) === 1 ? 'receipt' : 'receipts' ?> found</h2>
        <?php if ($bayError): ?>
          <p class="booking-error" role="alert">
            <?= te($bayError) ?>
            <?php if ($bayError === OKU_APPROVAL_REQUIRED): ?><a href="oku-verification.php"><?= te('OKU Parking Verification') ?></a><?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if (!$receipts): ?>
          <div class="card">
            <h3><?= te('No receipts for these dates') ?></h3>
            <p><?= te('Try a different date range or choose All receipts to see every transaction type.') ?></p>
          </div>
        <?php endif; ?>

        <ul class="receipt-list">
          <?php foreach ($receipts as $item):
              $itemUrl = $listUrl . '&id=' . $item['id'];
              $summary = $item['details']['Vehicle plate'] ?? $item['details']['Compound number'] ?? '';
              $isParking = $item['type'] === 'parking';
              $booking = $bookingsByReceipt[$item['id']] ?? null;
              $status = $booking['status'] ?? '';
              $expiry = $status === 'active' ? booking_expiry($booking) : null;
              $bayType = $booking ? parking_booking_bay_type($booking) : null;
          ?>
            <li class="card receipt-row"<?php if ($booking): ?> data-parking-session data-status="<?= e($status) ?>" data-plate="<?= e($booking['plate']) ?>"<?php endif; ?>>
              <div>
                <h3><?= te(receipt_title($item)) ?><?= $summary !== '' ? ' · ' . e($summary) : '' ?></h3>
                <p><time datetime="<?= e(date(DATE_ATOM, $item['at'])) ?>"><?= e(receipt_time($item['at'])) ?></time></p>
              </div>
              <p class="receipt-row-amount"><?= e(receipt_money($item['amount_cents'])) ?></p>
              <a class="btn" href="<?= e($itemUrl) ?>"><?= te('View receipt') ?><span class="receipt-sr-only"> <?= e($item['number']) ?></span></a>
              <?php if ($isParking): ?>
                <div class="receipt-parking-info">
                  <dl class="receipt-parking-details">
                    <div>
                      <dt><?= te('Location') ?></dt>
                      <dd><?= e($booking['lot'] ?? $item['details']['Location'] ?? '—') ?>
                        <?php if ($bayType !== null): ?><small><?= te(PARKING_BAY_TYPES[$bayType]) ?></small><?php endif; ?>
                      </dd>
                    </div>
                    <div><dt><?= te('Duration') ?></dt><dd><?= e($item['details']['Duration'] ?? '—') ?></dd></div>
                    <?php if ($booking): ?>
                      <div>
                        <dt><?= te('Status') ?></dt>
                        <dd>
                          <span class="tag <?= e($status) ?>"><?= te($status) ?></span>
                          <?php if ($expiry !== null): ?><span class="parking-timer" data-expires="<?= e($expiry) ?>">--:--:--</span><small class="timer-label"><?= te('Time remaining') ?></small><?php endif; ?>
                        </dd>
                      </div>
                    <?php endif; ?>
                  </dl>
                  <?php if ($booking): ?>
                    <div class="parking-session-actions"><?php require __DIR__ . '/includes/parking-session-actions.php'; ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  <?php elseif (!$receipt): ?>
    <section class="card">
      <h1><?= te('Receipt unavailable') ?></h1>
      <p><?= te('Only receipts from your account and from the past 30 days are available.') ?></p>
      <a class="btn" href="<?= e($listUrl) ?>"><?= te('Back to receipts') ?></a>
    </section>
  <?php else: ?>
    <a class="receipts-link receipt-back" href="<?= e($listUrl) ?>"><?= te('Back to receipts') ?></a>
    <article class="card receipt-detail" aria-labelledby="receipt-title">
      <p class="receipt-brand">MyPark Sabah</p>
      <h1 id="receipt-title"><?= te(receipt_title($receipt)) ?></h1>
      <dl class="receipt-details">
        <div><dt><?= te('Receipt number') ?></dt><dd><?= e($receipt['number']) ?></dd></div>
        <div><dt><?= te('Date (Malaysia time)') ?></dt><dd><time datetime="<?= e(date(DATE_ATOM, $receipt['at'])) ?>"><?= e(receipt_time($receipt['at'])) ?></time></dd></div>
        <?php foreach ($receipt['details'] as $label => $value): ?>
          <div><dt><?= te($label) ?></dt><dd><?= e($value) ?></dd></div>
        <?php endforeach; ?>
        <div class="receipt-total"><dt><?= te(receipt_amount_label($receipt)) ?></dt><dd><?= e(receipt_money($receipt['amount_cents'])) ?></dd></div>
      </dl>
      <p><?= te(receipt_payment_note($receipt)) ?></p>
      <div class="receipt-actions">
        <button type="button" onclick="window.print()"><?= te('Print or save as PDF') ?></button>
      </div>
    </article>
  <?php endif; ?>
</div>
<?php foot(false); ?>
