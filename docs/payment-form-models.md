# Payment Form Models

The payment form model is a special model used to both validate payment parameters and pass them to a payment gateway in a way that is expected by the gateway.

When returning after a validation error a `paymentForm` variable will be available to the template and will be set to an instance of `craft/commerce/models/payments/BasePaymentForm`.

Each gateway can use its own payment form, however it must extend `craft/commerce/models/payments/BasePaymentForm`. There are generic models available for use, specifically for gateways passing around credit card information, but you should refer to the documentation of the plugin providing the gateway to see if it uses its own model or not.

Generally, you shouldn’t be concerned with the specific type of the payment form model being used, as that is provided by the gateway and does not need to be configured.

## Model Attributes

The following attributes make up the default payment form model for gateways handling credit card information.

### token

If a token is found on the payment form, no validation of other field is performed and the data is ignored.

The token represents a pre validated credit card and is provided by a gateways client side JavaScript library. For example [Stripe.js](https://stripe.com/docs/stripe-js).

### firstName

The first name of the customers credit card.

Validation: required field

### lastName

The last name of the customers credit card.

Validation: required field

### month

Integer only number representing the month of credit card expiry.

Validation: required field, Min:1 Max: 12

### year

Integer only number representing the year of credit card expiry.

Validation: required field, Min: current year: 2016 Max: Current year plus 12 e.g 2028

### CVV

Integer only number found on the back side of the card for security.

Validation: minimum char length: 3, maximum char length: 4

### number

The credit card number itself.

Validation: [Luhn algorithm](https://en.wikipedia.org/wiki/Luhn_algorithm)

### threeDSecure

A flag indicating whether 3D Secure authentication is being performed for the transaction.

This property does not get validated.
