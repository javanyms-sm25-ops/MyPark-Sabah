<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$wallet =& demo_wallet();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $rawAmount = trim($_POST['amount'] ?? '');
    $amount = filter_var($rawAmount, FILTER_VALIDATE_FLOAT);

    if ($amount === false || !is_finite($amount) || $amount < 10 || $amount > 500 || fmod($amount, 10.0) !== 0.0) {
        $error = 'Top up amount must be between RM 10.00 and RM 500.00, in multiples of RM 10.';
    } else {
        $wallet['balance'] += $amount;
        $wallet['transactions'][] = [
            'type' => 'topup',
            'amount' => $amount,
            'created_at' => date('Y-m-d H:i'),
            'status' => 'paid'
        ];
        flash('Demo wallet topped up with RM ' . number_format($amount, 2) . '.');
        header('Location: wallet.php');
        exit;
    }
}

$transactions = array_reverse($wallet['transactions']);
head('Wallet');
?>

<p class="eyebrow"><?= te('MyPark WALLET') ?></p>
<h1><?= te('My wallet') ?></h1>

<div class="wallet-layout">
  <section class="wallet-balance fade-in">
    <small><?= te('Available balance') ?></small>
    <b>RM <?= number_format($wallet['balance'], 2) ?></b>
    <?php if (me()['role'] !== 'staff'): ?>
      <span><?= te('Use demo credit to') ?> <a href="compound.php"><?= te('pay compounds') ?></a>.</span>
    <?php endif; ?>
  </section>

  <section class="card wallet-topup">
    <h2><?= te('Top up wallet') ?></h2>
    <p class="muted"><?= te('This is a demo top up. Payment gateway connection is added later.') ?></p>
    <?php if ($error): ?><p class="error"><?= te($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="topup-options">
        <?php foreach ([10, 30, 50, 100] as $amount): ?>
          <button type="button" class="topup-choice" data-topup="<?= $amount ?>">RM <?= $amount ?></button>
        <?php endforeach; ?>
      </div>
      <label><?= te('Custom amount (RM)') ?><input id="topupAmount" type="number" name="amount" min="10" max="500" step="10" placeholder="10.00" required></label>
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
        <?php $isCompound = ($transaction['type'] ?? 'topup') === 'compound'; ?>
        <tr>
          <td><?= e($transaction['created_at']) ?></td>
          <td>
            <?php if ($isCompound): ?>
              Compound <?= e($transaction['compound']) ?><br><small>Receipt <?= e($transaction['reference']) ?></small>
            <?php else: ?><?= te('Top up') ?><?php endif; ?>
          </td>
          <td><?= $isCompound ? '−' : '+' ?> RM <?= number_format($transaction['amount'], 2) ?></td>
          <td><span class="tag paid"><?= te('Paid') ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><tr><td colspan="4"><?= te('No wallet activity yet.') ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
