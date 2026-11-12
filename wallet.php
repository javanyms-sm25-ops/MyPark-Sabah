<?php
require 'includes.php';
require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($amount < 10 || $amount > 500) {
        $error = 'Top up amount must be between RM 10.00 and RM 500.00.';
    } else {
        $_SESSION['demo_wallet_balance'] += $amount;
        $_SESSION['demo_wallet_transactions'][] = [
            'amount' => $amount,
            'created_at' => date('Y-m-d H:i'),
            'status' => 'paid'
        ];
        flash('Demo wallet topped up with RM ' . number_format($amount, 2) . '.');
        header('Location: wallet.php');
        exit;
    }
}

$transactions = array_reverse($_SESSION['demo_wallet_transactions']);
head('Wallet');
?>

<p class="eyebrow">MyPark WALLET</p>
<h1>My wallet</h1>

<div class="wallet-layout">
  <section class="wallet-balance fade-in">
    <small>Available balance</small>
    <b>RM <?= number_format($_SESSION['demo_wallet_balance'], 2) ?></b>
    <span>Use wallet credit for future parking payments.</span>
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

<h2>Top up history</h2>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($transactions as $transaction): ?>
        <tr><td><?= e($transaction['created_at']) ?></td><td>+ RM <?= number_format($transaction['amount'], 2) ?></td><td><span class="tag paid">Paid</span></td></tr>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><tr><td colspan="3">No wallet top ups yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php foot(); ?>
