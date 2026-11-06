import {config} from './api.js';

/**
 * Posts to the payment action, as the current session’s cart.
 *
 * @param {FormData} body Extra fields, such as a gateway’s payment form
 * @returns {Promise<{ok: boolean, json: object}>}
 */
export async function postPay(body) {
  body.set('action', 'commerce/payments/pay');
  body.set(config.csrf.name, config.csrf.token);

  const response = await fetch(config.payUrl, {
    method: 'POST',
    body,
    headers: {Accept: 'application/json'},
  });
  let json = {};
  try {
    json = await response.json();
  } catch {
    // Handled below, as a failure
  }

  return {ok: response.ok && json.success !== false && !json.error, json};
}

/**
 * Returns a message for a failed payment response.
 */
export function payErrorMessage(json) {
  const fieldErrors = Object.values(json.paymentFormErrors || {}).flat();
  return (
    fieldErrors[0] ||
    json.error ||
    json.message ||
    'Your payment could not be processed.'
  );
}

/**
 * Sends the customer on once the payment action has finished with them: to the gateway’s redirect (POSTing any
 * data it needs), or to the order’s return URL.
 */
export function followRedirect(json) {
  if (
    json.redirect &&
    json.redirectData &&
    Object.keys(json.redirectData).length
  ) {
    const form = Object.assign(document.createElement('form'), {
      method: 'POST',
      action: json.redirect,
    });
    for (const [name, value] of Object.entries(json.redirectData)) {
      form.appendChild(
        Object.assign(document.createElement('input'), {
          type: 'hidden',
          name,
          value,
        })
      );
    }
    document.body.appendChild(form);
    form.submit();
    return;
  }

  window.location.href = json.redirect || config.returnUrl || config.homeUrl;
}
