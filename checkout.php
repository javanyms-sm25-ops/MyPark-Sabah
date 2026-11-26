<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/paypal-checkout.php';
require_customer_wallet();
require_once __DIR__ . '/includes/parking-topup.php';
require_once __DIR__ . '/includes/receipt-data.php';
header('Cache-Control: private, no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $amount = $_POST['amount'] ?? '';
    $cents = paypal_amount_cents($amount);
    $parking = parking_topup_context(me()['id'], $_POST['parking'] ?? null);
    if (isset($_POST['parking']) && $parking === null) {
        flash('This booking is no longer saved. Choose your parking details again.');
        header('Location: parking-details.php', true, 303);
        exit;
    }
    if ($parking && parking_wallet_shortfall($parking['review']['fee'], demo_wallet()['balance']) === 0) {
        header('Location: ' . parking_topup_review_url($parking['topup']), true, 303);
        exit;
    }
    if ($cents === null) {
        $_SESSION['wallet_topup_invalid_amount'][me()['id']] = substr($amount, 0, 32);
        $walletUrl = $parking ? parking_topup_wallet_url($parking['topup']) : 'wallet.php#topupAmount';
        header('Location: ' . $walletUrl, true, 303);
        exit;
    }
    $_SESSION['paypal_checkout'] = [
        'user_id' => me()['id'],
        'cents' => $cents,
        'nonce' => bin2hex(random_bytes(16)),
    ] + ($parking ? ['parking' => $parking['topup']] : []);
    header('Location: checkout.php', true, 303);
    exit;
}

$checkout = $_SESSION['paypal_checkout'] ?? null;
if (!is_array($checkout) || ($checkout['user_id'] ?? null) !== me()['id']) $checkout = null;
$parking = parking_topup_context(me()['id'], $checkout['parking'] ?? null);
$walletUrl = $parking ? parking_topup_wallet_url($parking['topup']) : 'wallet.php';
$successUrl = $parking ? parking_topup_review_url($parking['topup']) : 'wallet.php?topup=complete';
$topupReceiptUrl = receipt_checkout_url(me()['id'], $checkout);
$config = paypal_config();
$configured = !empty($config['client_id']) && !empty($config['client_secret']);
head('Checkout');
?>
<link rel="stylesheet" href="assets/css/checkout.css">
<div class="checkout-page">
  <a class="btn btn-secondary checkout-back" href="<?= e($walletUrl) ?>">← <?= te($parking ? 'Change top-up amount' : 'Wallet') ?></a>
  <div class="checkout-card">
    <p class="eyebrow">MY PARK SABAH · SANDBOX CHECKOUT</p>
    <h1><?= te('Top up wallet') ?></h1>
    <?php if ($parking && empty($checkout['credited'])): ?>
      <p><?= te('After this top-up, you’ll return to your booking review.') ?></p>
    <?php endif; ?>
    <?php if ($checkout): ?>
      <div class="checkout-summary">
        <span><?= te(empty($checkout['credited']) ? 'Amount to pay' : 'Amount added') ?></span>
        <strong>RM <?= number_format($checkout['cents'] / 100, 2) ?></strong>
      </div>
      <?php if (!empty($checkout['credited'])): ?>
        <div class="payment-confirmation">
          <p role="status"><?= te('Your top-up is in your wallet.') ?></p>
          <?php if ($topupReceiptUrl): ?>
            <a class="btn" href="<?= e($topupReceiptUrl) ?>"><?= te('View receipt') ?></a>
          <?php endif; ?>
        </div>
        <a class="btn btn-secondary" href="<?= e($successUrl) ?>"><?= te($parking ? 'Back to booking review' : 'View wallet') ?></a>
      <?php elseif (!$configured): ?>
        <p class="checkout-error" role="alert"><?= te('PayPal Sandbox has not been set up on this server.') ?></p>
      <?php elseif (extension_loaded('curl')): ?>
        <div id="paypal-checkout" data-csrf="<?= e(csrf()) ?>" data-client-id="<?= e($config['client_id']) ?>"
          data-amount="<?= e(number_format($checkout['cents'] / 100, 2, '.', '')) ?>"
          data-order-id="<?= e($checkout['order_id'] ?? '') ?>" data-checkout-nonce="<?= e($checkout['nonce']) ?>"
          data-success-url="<?= e($successUrl) ?>">
          <p id="paypal-status" role="status" aria-live="polite"><?= te('Loading PayPal…') ?></p>
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
      <?php if ($parking && empty($checkout['credited'])): ?>
        <p><a href="<?= e($successUrl) ?>"><?= te('Back to booking review') ?></a></p>
      <?php endif; ?>
    <?php else: ?>
      <p><?= te('Select an amount in your wallet to start checkout.') ?></p>
      <a class="btn" href="wallet.php"><?= te('Choose amount') ?></a>
    <?php endif; ?>
  </div>
</div>
<?php foot(); ?>
