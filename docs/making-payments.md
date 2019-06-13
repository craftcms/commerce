# Making Payments

Once you’ve set up the store and payment gateways, here’s a quick example to get you started accepting payments.

It is assumed that the payment gateway is set on the cart and that the `cart` variable is available to the template as an instance of `craft\commerce\elements\Order`.

```twig
<form method="POST" class="form-horizontal">
    <input type="hidden" name="action" value="commerce/payments/pay"/>
    <input type="hidden" name="redirect" value="/commerce/customer/order?number={number}"/>
    <input type="hidden" name="cancelUrl" value="{{ '/commerce/checkout/payment'|hash }}"/>

    {{ csrfInput() }}

    {{ cart.gateway.getPaymentFormHtml({})|raw }}

    <button class="button button-primary" type="submit">Pay Now</button>
</form>
```

If you require custom markup and just applying CSS to a default markup won’t do, here’s what a simple credit card payment form markup might look like.

This example assumes the availability of a `paymentForm` variable, as discussed in [Payment Form Models](payment-form-models.md).

```twig

{% import "_includes/forms" as forms %}
<form method="POST" class="form-horizontal">
    <input type="hidden" name="action" value="commerce/payments/pay"/>
    <input type="hidden" name="redirect" value="/commerce/customer/order?number={number}"/>
    <input type="hidden" name="cancelUrl" value="{{ '/commerce/checkout/payment'|hash }}"/>

    {{ csrfInput() }}

    <fieldset class="card-holder">
        <legend>{{ 'Card Holder'|t('commerce') }}</legend>

        <div class="grid" data-cols="2">

            <!-- Card Holder Name -->
            <div class="item" data-colspan="1">
                {{ forms.text({
                    name: 'firstName',
                    maxlength: 70,
                    placeholder: "First Name"|t('commerce'),
                    autocomplete: false,
                    class: 'card-holder-first-name'~(paymentForm.getErrors('firstName') ? ' error'),
                    value: paymentForm.firstName,
                    required: true,
                }) }}
            </div>

            <div class="item" data-colspan="1">
                {{ forms.text({
                    name: 'lastName',
                    maxlength: 70,
                    placeholder: "Last Name"|t('commerce'),
                    autocomplete: false,
                    class: 'card-holder-last-name'~(paymentForm.getErrors('lastName') ? ' error'),
                    value: paymentForm.lastName,
                    required: true,
                }) }}
            </div>
        </div>

        {% set errors = [] %}
        {% for attributeKey in ['firstName', 'lastName'] %}
            {% set errors = errors|merge(paymentForm.getErrors(attributeKey)) %}
        {% endfor %}

        {{ forms.errorList(errors) }}
    </fieldset>

    <!-- Card Number -->
    <fieldset class="card-data">
        <legend>{{ 'Card'|t('commerce') }}</legend>

        <div class="multitext">
            <div class="multitextrow">

                {{ forms.text({
                    name: 'number',
                    maxlength: 19,
                    placeholder: "Card Number"|t('commerce'),
                    autocomplete: false,
                    class: 'card-number'~(paymentForm.getErrors('number') ? ' error'),
                    value: paymentForm.number
                }) }}

            </div>

            <div class="multitextrow">
                {{ forms.text({
                    class: 'card-expiry'~(paymentForm.getErrors('month') or paymentForm.getErrors('year') ? ' error'),
                    type: 'tel',
                    name: 'expiry',
                    placeholder: "MM"|t('commerce')~' / '~"YYYY"|t('commerce'),
                    value: paymentForm.expiry
                }) }}

                {{ forms.text({
                    type: 'tel',
                    name: 'cvv',
                    placeholder: "CVV"|t('commerce'),
                    class: 'card-cvc'~(paymentForm.getErrors('cvv') ? ' error'),
                    value: paymentForm.cvv
                }) }}
            </div>
        </div>

        {% set errors = [] %}
        {% for attributeKey in ['number', 'month', 'year', 'cvv'] %}
            {% set errors = errors|merge(paymentForm.getErrors(attributeKey)) %}
        {% endfor %}

        {{ forms.errorList(errors) }}

    </fieldset>

    <button class="button button-primary" type="submit">Pay Now</button>
</form>
```
