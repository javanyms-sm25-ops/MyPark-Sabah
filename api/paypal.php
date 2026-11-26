<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/paypal-checkout.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function paypal_reply(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

try {
    if (!me()) paypal_reply(['error' => 'Sign in again to continue checkout.'], 401);
    require_login('../pages/login.php');
    if (!can_use_customer_wallet(me())) paypal_reply(['error' => t(CUSTOMER_WALLET_REQUIRED)], 403);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') paypal_reply(['error' => 'Method not allowed.'], 405);
    check_csrf();
    $checkout =& $_SESSION['paypal_checkout'];
    if (!is_array($checkout) || ($checkout['user_id'] ?? null) !== me()['id']) {
        paypal_reply(['error' => 'Checkout has expired. Choose an amount again.'], 409);
    }
    $checkoutNonce = $_POST['checkout_nonce'] ?? null;
    if (($checkoutNonce !== null || isset($checkout['parking']))
        && !hash_equals($checkout['nonce'], $checkoutNonce ?? '')) {
        paypal_reply(['error' => t('This checkout has changed. Refresh the page and review the amount.')], 409);
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        if (!empty($checkout['order_id'])) paypal_reply(['orderId' => $checkout['order_id']]);
        $amount = number_format($checkout['cents'] / 100, 2, '.', '');
        $order = paypal_request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'wallet-topup',
                'custom_id' => $checkout['nonce'],
                'amount' => ['currency_code' => 'MYR', 'value' => $amount],
            ]],
        ], paypal_access_token(), $checkout['nonce']);
        if (!is_string($order['id'] ?? null) || !preg_match('/\A[A-Z0-9]{8,30}\z/', $order['id'])) {
            throw new RuntimeException('PayPal did not return an order ID.', 502);
        }
        $checkout['order_id'] = $order['id'];
        paypal_reply(['orderId' => $order['id']]);
    }
    if ($action === 'capture' || $action === 'verify') {
        $orderId = $_POST['order_id'] ?? '';
        if (!is_string($orderId) || $orderId === '' || !hash_equals((string)($checkout['order_id'] ?? ''), $orderId)) {
            paypal_reply(['error' => 'This payment does not match your checkout.'], 409);
        }
        if (!empty($checkout['credited'])) paypal_reply(['success' => true]);
        $transactions = MyParkData::$records['demo_wallets'][me()['id']]['transactions'] ?? [];
        foreach ($transactions as $transaction) {
            if (($transaction['type'] ?? null) === 'topup' && ($transaction['status'] ?? null) === 'paid'
                && ($transaction['reference'] ?? null) === $orderId) {
                $checkout['credited'] = true;
                paypal_reply(['success' => true]);
            }
        }
        $token = paypal_access_token();
        $orderPath = '/v2/checkout/orders/' . $orderId;
        $order = [];
        if ($action === 'capture') {
            try {
                $order = paypal_request('POST', $orderPath . '/capture', new stdClass(), $token, $checkout['nonce'] . '-capture');
            } catch (RuntimeException) {
                // PayPal may have captured the payment before the response was lost.
            }
        }
        if (!paypal_topup_verified($order, $checkout)) {
            // The capture response may omit fields available in the full order.
            $order = paypal_request('GET', $orderPath, null, $token);
        }
        if (!paypal_topup_verified($order, $checkout)) {
            $captureStatus = $order['purchase_units'][0]['payments']['captures'][0]['status'] ?? null;
            $orderStatus = $order['status'] ?? '';
            $canContinue = $captureStatus === null
                && in_array($orderStatus, ['CREATED', 'APPROVED', 'PAYER_ACTION_REQUIRED'], true);
            if ($action === 'verify' && $canContinue) paypal_reply(['success' => false]);
            $message = match (true) {
                $captureStatus === 'PENDING' => 'PayPal is still processing this payment. Refresh this page later for an update.',
                $canContinue => 'PayPal has not completed this payment. Try again using the payment options on this page.',
                $orderStatus === 'VOIDED', $captureStatus === 'DECLINED'
                    => 'PayPal did not complete this payment. Return to your wallet to start a new top-up.',
                default => 'PayPal could not verify this payment for your wallet. Refresh this page to check again.',
            };
            paypal_reply(['error' => $message], 409);
        }
        $wallet =& demo_wallet();
        $wallet['balance'] += $checkout['cents'] / 100;
        $wallet['transactions'][] = [
            'type' => 'topup',
            'amount' => $checkout['cents'] / 100,
            'created_at' => date('Y-m-d H:i'),
            'status' => 'paid',
            'reference' => $orderId,
        ];
        $checkout['credited'] = true;
        parking_payment_notice(me()['id'], $checkout['cents']);
        paypal_reply(['success' => true]);
    }
    paypal_reply(['error' => 'Unknown checkout action.'], 400);
} catch (RuntimeException $error) {
    $status = in_array($error->getCode(), [400, 401, 403, 409, 502, 503], true) ? $error->getCode() : 502;
    paypal_reply(['error' => $error->getMessage()], $status);
}
