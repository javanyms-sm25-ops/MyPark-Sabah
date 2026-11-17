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

<p class="eyebrow">MyPark WALLET</p>
<h1>My wallet</h1>

<div class="wallet-layout">
  <section class="wallet-balance fade-in">
    <small>Available balance</small>
    <b>RM <?= number_format($wallet['balance'], 2) ?></b>
    <?php if (me()['role'] !== 'staff'): ?>
      <span>Use demo credit to <a href="compound.php">pay compounds</a>.</span>
    <?php endif; ?>
  </section>

  <section class="card wallet-topup">
    <h2>Top up wallet</h2>
    <p class="muted">This is a demo top up. Payment gateway connection is added later.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="topup-options">
        <?php foreach ([10, 30, 50, 100] as $amount): ?>
          <button type="button" class="topup-choice" data-topup="<?= $amount ?>">RM <?= $amount ?></button>
        <?php endforeach; ?>
      </div>
      <label>Custom amount (RM)<input id="topupAmount" type="number" name="amount" min="10" max="500" step="10" placeholder="10.00" required></label>
      <button>Top up now</button>
    </form>
  </section>
</div>

<h2>Wallet activity</h2>
<p><a class="btn" href="receipts.php?type=topup">View wallet top-up receipts</a></p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Transaction</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($transactions as $transaction): ?>
        <?php $isCompound = ($transaction['type'] ?? 'topup') === 'compound'; ?>
        <tr>
          <td><?= e($transaction['created_at']) ?></td>
          <td>
            <?php if ($isCompound): ?>
              Compound <?= e($transaction['compound']) ?><br><small>Receipt <?= e($transaction['reference']) ?></small>
            <?php else: ?>Top up<?php endif; ?>
          </td>
          <td><?= $isCompound ? '−' : '+' ?> RM <?= number_format($transaction['amount'], 2) ?></td>
          <td><span class="tag paid">Paid</span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><tr><td colspan="4">No wallet activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
