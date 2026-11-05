const config = JSON.parse(document.getElementById('checkout-config').textContent);

export {config};

const MONEY = 'amount formatted';
const ADDRESS = `id fullName firstName lastName organization addressLine1 addressLine2 addressLine3 locality administrativeArea postalCode countryCode`;

export const CART_FIELDS = `
  number email currency couponCode isCompleted totalQty requiresShipping requireShippingAddress requireBillingAddress storeName
  lineItems { id description sku qty options imageUrl price { ${MONEY} } salePrice { ${MONEY} } subtotal { ${MONEY} } total { ${MONEY} } }
  adjustments { type name description included amount { ${MONEY} } }
  itemSubtotal { ${MONEY} } totalDiscount { ${MONEY} } totalShippingCost { ${MONEY} } totalTax { ${MONEY} } totalTaxIncluded { ${MONEY} } total { ${MONEY} }
  outstandingBalance { ${MONEY} }
  canCheckout blockedReason partialPayments { percentage token amount { ${MONEY} } }
  shippingAddress { ${ADDRESS} } billingAddress { ${ADDRESS} } billingSameAsShipping
  shippingMethodHandle shippingMethods { handle name price { ${MONEY} } }
  gatewayId gateways { id handle name type fieldNamespace publishableKey supportsPartialPayment }
`;

export class ExpiredError extends Error {}

/**
 * Sends a GraphQL operation to the checkout endpoint.
 */
export async function gql(query, variables = {}) {
  const response = await fetch(config.graphqlUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${config.token}`,
    },
    body: JSON.stringify({query, variables}),
  });

  let json;
  try {
    json = await response.json();
  } catch {
    throw new Error('Something went wrong. Please try again.');
  }

  if (json.errors?.length) {
    if (json.errors[0].extensions?.code === 'EXPIRED') {
      throw new ExpiredError(json.errors[0].message);
    }
    throw new Error(json.errors[0].message);
  }

  return json.data;
}

export {ADDRESS};
