<?php
require __DIR__ . '/includes/bootstrap.php';
require_customer_wallet();
header('Cache-Control: private, no-store');

$wallet =& demo_wallet();
$transactions = array_reverse($wallet['transactions']);
$userId = me()['id'];
$bookings = array_column($_SESSION['demo_bookings'], null, 'id');
$invalidAmount = $_SESSION['wallet_topup_invalid_amount'][$userId] ?? null;
unset($_SESSION['wallet_topup_invalid_amount'][$userId]);
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

  <section class="card wallet-topup">
    <h2><?= te('Top up wallet') ?></h2>
    <p class="muted">Pay securely with PayPal Sandbox at checkout.</p>
    <form method="post" action="checkout.php">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="topup-options">
        <?php foreach ([10, 30, 50, 100] as $amount): ?>
          <button type="button" class="topup-choice" data-topup="<?= $amount ?>">RM <?= $amount ?></button>
        <?php endforeach; ?>
      </div>
      <div class="wallet-topup-amount">
        <label for="topupAmount"><?= te('Custom amount (RM)') ?></label>
        <input id="topupAmount" type="number" name="amount" min="1" max="500" step="0.01" placeholder="1.00"
          value="<?= e($invalidAmount ?? '') ?>" aria-describedby="topupAmountFeedback"
          <?= $invalidAmount !== null ? 'aria-invalid="true"' : '' ?> required>
        <p class="field-feedback" id="topupAmountFeedback" aria-live="polite" aria-atomic="true"
          data-message="<?= $amountError ?>"><?= $invalidAmount !== null ? $amountError : '' ?></p>
      </div>
      <button><?= te('Top up now') ?></button>
    </form>
  </section>
</div>

<h2><?= te('Wallet activity') ?></h2>
<p><a class="btn" href="receipts.php?type=topup"><?= te('View wallet top-up receipts') ?></a></p>
<div class="table-wrap">
  <table>
    <thead><tr><th><?= te('Date') ?></th><th><?= te('Transaction') ?></th><th><?= te('Amount') ?></th><th><?= te('Status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($transactions as $transaction): ?>
        <?php $type = $transaction['type'] ?? 'topup'; ?>
        <tr>
          <td><?= e($transaction['created_at']) ?></td>
          <td>
            <?php if ($type === 'compound'): ?>
              Compound <?= e($transaction['compound']) ?><br><small>Receipt <?= e($transaction['reference']) ?></small>
            <?php elseif ($type === 'parking'): ?>
              <?php if (($bookings[$transaction['booking_id']]['user_id'] ?? null) === $userId): ?>
                <a href="ticket.php?id=<?= e($transaction['booking_id']) ?>"><?= te('Parking booking') ?> #<?= e($transaction['booking_id']) ?></a>
              <?php else: ?>
                <?= te('Parking booking') ?> #<?= e($transaction['booking_id']) ?>
              <?php endif; ?>
              <br><small><?= e($transaction['location']) ?></small>
            <?php else: ?><?= te('Top up') ?><?php endif; ?>
          </td>
          <td><?= in_array($type, ['compound', 'parking'], true) ? '−' : '+' ?> RM <?= number_format($transaction['amount'], 2) ?></td>
          <td><span class="tag paid"><?= te('Paid') ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><tr><td colspan="4"><?= te('No wallet activity yet.') ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
