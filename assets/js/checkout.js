(() => {
  'use strict';

  const checkout = document.getElementById('paypal-checkout');
  if (!checkout) return;
  const status = document.getElementById('paypal-status');
  const button = document.getElementById('paypal-button');
  const cardContainer = document.getElementById('paypal-card');
  const cardButton = document.getElementById('paypal-card-button');
  const methodChoices = document.getElementById('checkout-methods');
  const translate = window.MyParkLocale?.text ?? (message => message);
  const successUrl = checkout.dataset.successUrl || 'wallet.php';
  let busy = false;

  function setBusy(value) {
    busy = value;
    methodChoices.disabled = value;
  }

  function showStatus(message) {
    status.textContent = translate(message);
  }

  async function request(action, orderId = '') {
    const data = new URLSearchParams({ action, csrf: checkout.dataset.csrf, order_id: orderId });
    if (checkout.dataset.checkoutNonce) data.set('checkout_nonce', checkout.dataset.checkoutNonce);
    const response = await fetch('api/paypal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data,
      credentials: 'same-origin',
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Payment could not be completed.');
    return result;
  }

  async function confirmPayment(orderId, redirectOnSuccess = true) {
    showStatus('Confirming your payment…');
    try {
      const result = await request('capture', orderId);
      if (result.success !== true) throw new Error('PayPal could not confirm the payment.');
      if (redirectOnSuccess) window.location.assign(successUrl);
      return true;
    } catch (error) {
      showStatus(error.message);
      setBusy(false);
      return false;
    }
  }

  async function createOrderId() {
    const result = await request('create');
    if (typeof result.orderId !== 'string' || !result.orderId.length) {
      throw new Error('PayPal did not return a valid order ID.');
    }
    return result.orderId;
  }

  async function initialize() {
    try {
      if (checkout.dataset.orderId) {
        showStatus('Checking your payment status…');
        const result = await request('verify', checkout.dataset.orderId);
        if (result.success === true) {
          window.location.assign(successUrl);
          return;
        }
      }
      if (!window.paypal) throw new Error('PayPal could not load. Refresh the page and try again.');
      const clientId = checkout.dataset.clientId;
      if (!clientId) throw new Error('PayPal is unavailable. Please try again later.');
      const sdk = await window.paypal.createInstance({
        clientId,
        components: ['paypal-payments', 'paypal-guest-payments'],
        pageType: 'checkout',
      });
      const methods = await sdk.findEligibleMethods({
        currencyCode: 'MYR',
        amount: checkout.dataset.amount,
      });
      const callbacks = {
        onApprove({ orderId }) {
          return confirmPayment(orderId);
        },
        onCancel() {
          const message = 'Payment cancelled. Your wallet balance is unchanged.';
          if (window.MyParkToast) {
            showStatus('');
            window.MyParkToast.show(message, 'info');
          } else {
            showStatus(message);
          }
          setBusy(false);
        },
        onError() {
          showStatus('PayPal could not complete this payment. Please try again.');
          setBusy(false);
        },
      };

      function attachPayment(paymentButton, session, visibleElement = paymentButton) {
        visibleElement.hidden = false;
        paymentButton.addEventListener('click', async () => {
          if (busy) return;
          setBusy(true);
          showStatus('Opening PayPal…');
          try {
            const orderPromise = createOrderId().then(orderId => ({ orderId }));
            await session.start({ presentationMode: 'auto' }, orderPromise);
          } catch (error) {
            showStatus(error.message);
            setBusy(false);
          }
        });
      }

      if (methods.isEligible('paypal')) {
        attachPayment(button, sdk.createPayPalOneTimePaymentSession(callbacks));
      }
      async function setupGuestCard() {
        if (!methods.isEligible('card')) return;
        let cardConfirmation;
        const cardSession = await sdk.createPayPalGuestOneTimePaymentSession({
          ...callbacks,
          onApprove({ orderId }) {
            cardConfirmation = confirmPayment(orderId, false);
            return cardConfirmation;
          },
          async onComplete() {
            if (await cardConfirmation) window.location.assign(successUrl);
          },
        });
        attachPayment(cardButton, cardSession, cardContainer);
      }

      status.textContent = '';
      try {
        await setupGuestCard();
      } catch {
        cardContainer.hidden = true;
      }
      if (button.hidden && cardContainer.hidden) {
        showStatus('PayPal payment methods are unavailable for this account or amount.');
      }
    } catch (error) {
      showStatus(error.message || 'PayPal could not load. Refresh the page and try again.');
    }
  }

  window.addEventListener('load', initialize, { once: true });
})();
