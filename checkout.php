<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/paypal-checkout.php';
require_login();
header('Cache-Control: private, no-store');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $cents = paypal_amount_cents($_POST['amount'] ?? null);
    if ($cents === null) {
        unset($_SESSION['paypal_checkout']);
        $error = 'Enter an amount from RM 1.00 to RM 500.00, with up to two decimal places.';
    } else {
        $_SESSION['paypal_checkout'] = [
            'user_id' => me()['id'],
            'cents' => $cents,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        header('Location: checkout.php', true, 303);
        exit;
    }
}

$checkout = $_SESSION['paypal_checkout'] ?? null;
if (!is_array($checkout) || ($checkout['user_id'] ?? null) !== me()['id']) $checkout = null;
$config = paypal_config();
$available = !empty($config['client_id']) && !empty($config['client_secret']) && extension_loaded('curl');
head('Checkout');
?>
<link rel="stylesheet" href="assets/css/checkout.css">
<div class="checkout-page">
  <a class="checkout-back" href="wallet.php">← <?= te('Wallet') ?></a>
  <div class="checkout-card">
    <p class="eyebrow">MY PARK SABAH · SANDBOX CHECKOUT</p>
    <h1>Top up wallet</h1>
    <?php if ($error): ?><p class="checkout-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($checkout): ?>
      <div class="checkout-summary">
        <span>Amount to pay</span>
        <strong>RM <?= number_format($checkout['cents'] / 100, 2) ?></strong>
      </div>
      <?php if (!empty($checkout['credited'])): ?>
        <p role="status">This payment has already been credited to your wallet.</p>
        <a class="btn" href="wallet.php">View wallet</a>
      <?php elseif ($available): ?>
        <div id="paypal-checkout" data-csrf="<?= e(csrf()) ?>" data-client-id="<?= e($config['client_id']) ?>"
          data-amount="<?= e(number_format($checkout['cents'] / 100, 2, '.', '')) ?>"
          data-order-id="<?= e($checkout['order_id'] ?? '') ?>">
          <p id="paypal-status" role="status" aria-live="polite">Loading PayPal…</p>
          <paypal-button id="paypal-button" type="pay" hidden></paypal-button>
          <paypal-basic-card-container id="paypal-card" hidden>
            <paypal-basic-card-button id="paypal-card-button"></paypal-basic-card-button>
          </paypal-basic-card-container>
        </div>
        <script src="assets/js/checkout.js" defer></script>
        <script src="https://www.sandbox.paypal.com/web-sdk/v6/core" defer></script>
      <?php else: ?>
        <p class="checkout-error" role="alert">PayPal Sandbox is unavailable. Please try again later.</p>
      <?php endif; ?>
    <?php else: ?>
      <p>Select an amount in your wallet to start checkout.</p>
      <a class="btn" href="wallet.php">Choose amount</a>
    <?php endif; ?>
  </div>
</div>
<?php foot(); ?>
