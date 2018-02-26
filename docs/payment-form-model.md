# Payment Form Model

The payment form model is a special model used to validate a credit card when payment is submitted. It is never saved to the server. It is only created when making a payment, and returned to the template if validation errors were found on the model or from the payment gateway. When returning after a validation error a `paymentForm` variable will be available to the template.

The `paymentForm` model is usually only used by onsite gateways. In addition to the following attributes some gateways require other additional ones. See the [Payment Gateways](payment-gateways.md) page for gateway specific information.

# Model Attributes

The follow attributes make up the payment form model.

## token

If a token is found on the payment form, no validation of other field is performed and the data is ignored.

The token represents a pre validated credit card and is provided by a gateways client side javascript library. For example [Stripe.js](https://stripe.com/docs/stripe.js/switching)

## firstName

The first name of the customers credit card.

Validation: required field

## lastName

The last name of the customers credit card.

Validation: required field

## month

Integer only number representing the month of credit card expiry.

Validation: required field, Min:1 Max: 12

## year

Integer only number representing the year of credit card expiry.

Validation: required field, Min: current year: 2016 Max: Current year plus 12 e.g 2028

## CVV

Integer only number found on the back side of the card for security.

Validation: minimum char length: 3, maximum char length: 4

## number

The credit card number itself.

Validation: [Luhn algorithm](https://en.wikipedia.org/wiki/Luhn_algorithm)

## Example Usage

Below is an example of a payment form using the payment form model.

```twig

<form method="POST" class="form-horizontal">
<input type="hidden" name="action" value="commerce/payments/pay"/>
<input type="hidden" name="redirect" value="/commerce/customer/order?number={number}"/>
<input type="hidden" name="cancelUrl" value="/commerce/checkout/payment"/>
{{ getCsrfInput() }}

{% set formValues = {
firstName: paymentForm is defined ? paymentForm.firstName : (cart.billingAddress ? cart.billingAddress.firstName : ''),
lastName: paymentForm is defined ? paymentForm.lastName : (cart.billingAddress ? cart.billingAddress.lastName : ''),
number: paymentForm is defined ? paymentForm.number : '',
cvv: paymentForm is defined ? paymentForm.cvv : '',
month: paymentForm is defined ? paymentForm.month : 1,
year: paymentForm is defined ? paymentForm.year : currentYear,
} %}

<input type="text" name="firstName" value="{{ formValues.firstName }}">
{% if paymentForm is defined %}{{ paymentForm.getError('firstName') }}{% endif %}

<input type="text" name="lastName" value="{{ formValues.lastName }}">
{% if paymentForm is defined %}{{ paymentForm.getError('lastName') }}{% endif %}

<input type="text" name="number" placeholder="Card Number" value="{{ formValues.number }}">
{% if paymentForm is defined %}{{ paymentForm.getError('number') }}{% endif %}

<select name="month">
 {% for month in 1..12 %}
  <option value="{{ month }}" {% if formValues.month == month %}selected{% endif %}>{{ month }}</option>
 {% endfor %}
</select>
{% if paymentForm is defined %}{{ paymentForm.getError('month') }}{% endif %}

<select class="required form-control" name="year">
{% for year in currentYear-1..(currentYear + 12) %}
<option value="{{ year }}"{% if formValues.year == year %}selected{% endif %}>{{ year }}</option>
{% endfor %}
</select>
{% if paymentForm is defined %}{{ paymentForm.getError('year') }}{% endif %}

<input type="text" name="cvv" placeholder="CVV" maxlength="4" value="{{ formValues.cvv }}">
{% if paymentForm is defined %}{{ paymentForm.getError(cvv) }}{% endif %}

<button class="button button-primary" type="submit">Pay Now</button>
</form>
```
