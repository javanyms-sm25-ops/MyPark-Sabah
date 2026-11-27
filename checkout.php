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
$credited = !empty($checkout['credited']);
head('Checkout');
?>
<link rel="stylesheet" href="assets/css/checkout.css">
<div class="checkout-page">
  <a class="checkout-back" href="<?= e($walletUrl) ?>"><span aria-hidden="true">←</span> <?= te($parking ? 'Change top-up amount' : 'Back to wallet') ?></a>
  <div class="checkout-heading">
    <p class="eyebrow"><?= te('Checkout') ?></p>
    <h1><?= te('Top up wallet') ?></h1>
  </div>
  <?php if ($checkout): ?>
    <div class="checkout-layout">
      <section class="checkout-card checkout-summary" aria-labelledby="checkout-summary-title">
        <h2 id="checkout-summary-title"><?= te('Order summary') ?></h2>
        <p class="checkout-item"><?= te('Wallet top-up') ?></p>
        <dl class="checkout-total">
          <dt><?= te($credited ? 'Amount added' : 'Amount to pay') ?></dt>
          <dd><?= e(receipt_money($checkout['cents'])) ?></dd>
        </dl>
        <?php if ($parking && !$credited): ?>
          <p class="muted"><?= te('After this top-up, you’ll return to your booking review.') ?></p>
          <a href="<?= e($successUrl) ?>"><?= te('Back to booking review') ?></a>
        <?php endif; ?>
      </section>
      <section class="checkout-card checkout-payment" aria-labelledby="checkout-payment-title">
        <h2 id="checkout-payment-title"><?= te($credited ? 'Top-up complete' : 'Payment method') ?></h2>
        <?php if ($credited): ?>
          <p class="checkout-environment">PayPal Sandbox</p>
          <div class="payment-confirmation">
            <p role="status"><?= te('Your top-up is in your wallet.') ?></p>
            <?php if ($topupReceiptUrl): ?>
              <a class="btn" href="<?= e($topupReceiptUrl) ?>"><?= te('View receipt') ?></a>
            <?php endif; ?>
          </div>
          <a class="btn btn-secondary" href="<?= e($successUrl) ?>"><?= te($parking ? 'Back to booking review' : 'View wallet') ?></a>
        <?php else: ?>
          <fieldset class="checkout-methods" id="checkout-methods">
            <legend class="validation-sr-only"><?= te('Payment method') ?></legend>
            <?php foreach (['paypal' => 'PayPal', 'card' => 'Demo credit or debit card', 'fpx' => 'Demo FPX', 'qr' => 'Demo DuitNow QR'] as $method => $label): ?>
              <label><input type="radio" name="checkout-method" value="<?= e($method) ?>" <?= $method === 'paypal' ? 'checked' : '' ?>> <?= te($label) ?></label>
            <?php endforeach; ?>
          </fieldset>
          <div data-payment-panel="paypal">
            <p class="checkout-environment">PayPal Sandbox</p>
            <?php if (!$configured): ?>
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
              <p class="checkout-error" role="alert"><?= te('PayPal Sandbox is unavailable. Please try again later.') ?></p>
            <?php endif; ?>
          </div>
          <div data-payment-panel="card" hidden>
            <?php // These sample fields are local only. Single-row text areas avoid payment and password autofill. ?>
            <form class="checkout-card-fields" id="checkout-demo-card" autocomplete="off" novalidate>
              <label class="checkout-card-field checkout-card-field--wide"><?= te('Name on card') ?>
                <textarea rows="1" autocomplete="off" spellcheck="false" placeholder="<?= te('e.g. Alex Tan') ?>" maxlength="80" required></textarea>
              </label>
              <label class="checkout-card-field checkout-card-field--wide"><?= te('Card number') ?>
                <textarea rows="1" autocomplete="off" spellcheck="false" inputmode="numeric" placeholder="1234 1234 1234 1234" maxlength="19" required
                  data-format="1234 ?1234 ?1234 ?1234" data-invalid="<?= te('Use the test card number 1234 1234 1234 1234.') ?>"></textarea>
              </label>
              <label class="checkout-card-field"><?= te('Expiry date') ?>
                <textarea rows="1" autocomplete="off" spellcheck="false" inputmode="numeric" placeholder="MM/YY" maxlength="5" required
                  data-format="(?:0[1-9]|1[0-2])/?[0-9]{2}" data-invalid="<?= te('Enter the expiry date as MM/YY.') ?>"></textarea>
              </label>
              <label class="checkout-card-field"><?= te('Security code (CVV/CVC)') ?>
                <textarea rows="1" autocomplete="off" spellcheck="false" inputmode="numeric" placeholder="<?= te('3 or 4 digits') ?>" maxlength="4" required
                  data-format="[0-9]{3,4}" data-invalid="<?= te('Enter a 3 or 4 digit test code.') ?>"></textarea>
              </label>
              <button type="submit" id="checkout-card-submit" disabled><?= te('Pay {amount}', ['amount' => receipt_money($checkout['cents'])]) ?></button>
            </form>
          </div>
          <div data-payment-panel="fpx" hidden>
            <p class="muted"><?= te('Simulation only. No money is charged or added to your wallet.') ?></p>
            <label for="checkout-fpx-bank"><?= te('Select a test bank') ?></label>
            <select id="checkout-fpx-bank">
              <option value=""><?= te('Choose a bank') ?></option>
              <option>Maybank</option><option>CIMB Bank</option><option>Public Bank</option>
            </select>
            <button type="button" data-simulate-payment disabled><?= te('Simulate payment') ?></button>
          </div>
          <div class="checkout-qr-panel" data-payment-panel="qr" hidden>
            <p class="muted"><?= te('Simulation only. No money is charged or added to your wallet.') ?></p>
            <figure class="checkout-qr-card">
              <div class="checkout-qr-face">
                <strong>Duit<span>Now</span> QR</strong>
                <div id="checkout-demo-qr" role="img" aria-label="<?= te('Sample QR code for testing') ?>"
                  data-qr-text="<?= e('MyPark sample QR | No payment | ' . receipt_money($checkout['cents'])) ?>"></div>
                <span>MyPark</span>
              </div>
              <figcaption><?= te('Sample QR code for testing') ?></figcaption>
            </figure>
            <button type="button" data-simulate-payment><?= te('Simulate payment') ?></button>
          </div>
          <p id="checkout-simulation-status" role="status" data-complete="<?= te('Simulation complete. Your wallet balance is unchanged.') ?>"></p>
          <noscript><?= te('Enable JavaScript to choose a payment method.') ?></noscript>
          <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
          <script src="assets/js/checkout-methods.js" defer></script>
        <?php endif; ?>
      </section>
    </div>
  <?php else: ?>
    <div class="checkout-card checkout-empty">
      <p><?= te('Select an amount in your wallet to start checkout.') ?></p>
      <a class="btn" href="wallet.php"><?= te('Choose amount') ?></a>
    </div>
  <?php endif; ?>
</div>
<?php foot(); ?>
