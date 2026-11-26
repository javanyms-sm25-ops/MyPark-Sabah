<?php
require __DIR__ . '/includes/bootstrap.php';
require_customer_wallet();
require_once __DIR__ . '/includes/parking-topup.php';
require_once __DIR__ . '/includes/paypal-checkout.php';
header('Cache-Control: private, no-store');

$wallet =& demo_wallet();
$transactions = array_reverse($wallet['transactions']);
$userId = me()['id'];
$bookings = array_column(MyParkData::$records['demo_bookings'], null, 'id');
$invalidAmount = $_SESSION['wallet_topup_invalid_amount'][$userId] ?? null;
if (ajax_table_fragment() === null) unset($_SESSION['wallet_topup_invalid_amount'][$userId]);
$parking = parking_topup_context($userId, $_GET['parking'] ?? null);
$parkingReview = $parking['review'] ?? null;
$shortfall = $parkingReview ? parking_wallet_shortfall($parkingReview['fee'], $wallet['balance']) : 0;
$suggested = $shortfall > 0 ? number_format(max(PAYPAL_TOPUP_MIN_CENTS, $shortfall) / 100, 2, '.', '') : '';
$amountError = te('Enter an amount from RM 1.00 to RM 500.00, with up to two decimal places.');
head('Wallet');
?>

<p class="eyebrow"><?= te('MyPark WALLET') ?></p>
<h1><?= te('My wallet') ?></h1>

<div class="wallet-layout">
  <section class="wallet-balance fade-in">
    <small><?= te('Available balance') ?></small>
    <b>RM <?= number_format($wallet['balance'], 2) ?></b>
    <span><?= te('Use demo credit to') ?> <a href="compound.php"><?= te('pay compounds') ?></a>.</span>
  </section>

  <section class="card wallet-topup" id="bookingTopup" aria-labelledby="wallet-topup-title">
    <h2 id="wallet-topup-title"><?= te('Top up wallet') ?></h2>
    <?php if ($parkingReview): ?>
      <div class="booking-wallet-notice<?= $shortfall > 0 ? ' booking-wallet-notice--shortfall' : '' ?>">
        <strong><?= e($parkingReview['lot']['name']) ?></strong>
        <p><?= e($parkingReview['plate']) ?> · <?= te($parkingReview['hours'] === 1 ? '{count} hour' : '{count} hours', ['count' => $parkingReview['hours']]) ?></p>
        <p><?= $shortfall > 0
          ? te('You need RM {amount} more', ['amount' => number_format($shortfall / 100, 2)])
          : te('Your wallet has enough for this booking.') ?></p>
        <?php if ($shortfall > 0 && $shortfall < PAYPAL_TOPUP_MIN_CENTS): ?>
          <p><?= te('The minimum top-up is RM 1.00.') ?></p>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(parking_topup_review_url($parking['topup'])) ?>"><?= te('Back to booking review') ?></a>
      </div>
    <?php elseif (isset($_GET['parking'])): ?>
      <p class="muted"><?= te('This booking is no longer saved. Choose your parking details again.') ?></p>
      <a class="btn btn-secondary" href="find_parking.php"><?= te('Find parking') ?></a>
    <?php endif; ?>
    <?php if (!$parkingReview || $shortfall > 0): ?>
    <p class="muted"><?= te('Pay with PayPal Sandbox at checkout.') ?></p>
    <form method="post" action="checkout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <?php if ($parking): ?><input type="hidden" name="parking" value="<?= e($parking['topup']) ?>"><?php endif; ?>
      <div class="topup-options">
        <?php foreach ([10, 30, 50, 100] as $amount): ?>
          <button type="button" class="topup-choice" data-topup="<?= $amount ?>">RM <?= $amount ?></button>
        <?php endforeach; ?>
      </div>
      <div class="wallet-topup-amount">
        <label for="topupAmount"><?= te('Custom amount (RM)') ?></label>
        <input id="topupAmount" type="number" name="amount" min="1" max="500" step="0.01" placeholder="1.00"
          value="<?= e($invalidAmount ?? $suggested) ?>" aria-describedby="topupAmountFeedback"
          <?= $invalidAmount !== null ? 'aria-invalid="true"' : '' ?> required>
        <p class="field-feedback" id="topupAmountFeedback" aria-live="polite" aria-atomic="true"
          data-message="<?= $amountError ?>"><?= $invalidAmount !== null ? $amountError : '' ?></p>
      </div>
      <button><?= te('Top up now') ?></button>
    </form>
    <?php endif; ?>
  </section>
</div>

<h2><?= te('Wallet activity') ?></h2>
<p><a class="btn" href="receipts.php?type=topup"><?= te('View wallet top-up receipts') ?></a></p>
<?php
$transactionTypes = ['topup' => 'Top up', 'parking' => 'Parking booking', 'compound' => 'Compounds'];
$view = ajax_table_view('wallet-activity', $transactions, [
    'created_at' => 'Date', 'transaction' => 'Transaction', 'amount' => 'Amount', 'status' => 'Status',
], [
    'values' => static function (array $transaction) use ($transactionTypes): array {
        $type = $transaction['type'] ?? 'topup';
        return [
            'created_at' => $transaction['created_at'],
            'transaction' => t($transactionTypes[$type] ?? 'Top up') . ' ' . ($transaction['location'] ?? '')
                . ' ' . ($transaction['compound'] ?? '') . ' ' . ($transaction['reference'] ?? '') . ' ' . ($transaction['booking_id'] ?? ''),
            'type' => $type,
            'amount' => in_array($type, ['parking', 'compound'], true) ? -(float)$transaction['amount'] : (float)$transaction['amount'],
            'status' => t('Paid'),
        ];
    },
    'filter' => ['label' => 'Transaction', 'field' => 'type', 'choices' => ['' => 'All transactions'] + $transactionTypes],
    'empty' => 'No wallet activity yet.', 'numeric' => ['amount'],
]);
ajax_table_begin($view);
?>
<div class="table-wrap">
  <table>
    <?php ajax_table_head($view); ?>
    <tbody>
      <?php foreach ($view['rows'] as $transaction): ?>
        <?php $type = $transaction['type'] ?? 'topup'; ?>
        <tr>
          <td><?= e($transaction['created_at']) ?></td>
          <td>
            <?php if ($type === 'compound'): ?>
              Compound <?= e($transaction['compound']) ?><br><small>Receipt <?= e($transaction['reference']) ?></small>
            <?php elseif ($type === 'parking'): ?>
              <?php if (($bookings[$transaction['booking_id']]['user_id'] ?? null) === $userId): ?>
                <a class="btn btn-secondary" href="ticket.php?id=<?= e($transaction['booking_id']) ?>"><?= te('Parking booking') ?> #<?= e($transaction['booking_id']) ?></a>
              <?php else: ?>
                <?= te('Parking booking') ?> #<?= e($transaction['booking_id']) ?>
              <?php endif; ?>
              <br><small><?= e($transaction['location']) ?></small>
            <?php else: ?><?= te('Top up') ?><?php endif; ?>
          </td>
          <td class="ajax-table-number"><?= in_array($type, ['compound', 'parking'], true) ? '−' : '+' ?> RM <?= number_format($transaction['amount'], 2) ?></td>
          <td><span class="tag paid"><?= te('Paid') ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php ajax_table_empty($view); ?>
    </tbody>
  </table>
</div>

<?php ajax_table_end($view); foot(); ?>
