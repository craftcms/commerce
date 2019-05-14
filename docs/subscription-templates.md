# Subscription Templates

Once you’ve familiarized yourself with how [subscriptions](subscriptions.md) work and set up your own subscription plans, you’re ready to write some subscription templates.

When creating templates for subscription actions, if you don’t want to use the default template provided to you by the gateway, you’ll have to reference to the plugin documentation that is providing the gateway to see, what parameters are available to you.

This documentation is intended to give you a head start in getting subscriptions working as well as to show the correct endpoints for subscription actions.

## Subscribing

For starting a subscription, the following example is a good start. A thing to note is that gateways handle payment sources used for the subscription differently, so that might affect your template.

```twig
{% set plans = craft.commerce.getPlans().getAllPlans() %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/subscriptions/subscribe">

    {{ csrfInput() }}

    <div>
        <select name="planUid" id="planSelect">
            {% for plan in plans %}
                <option value="{{ plan.uid|hash }}">{{ plan.name }}</option>
            {% endfor %}
        </select>
    </div>

    {# Display only if the user does not have a payment soure saved #}
    {# {{ plan.getGateway().getPaymentFormHtml({})|raw }} #}

    <button type="submit">{{ "Subscribe"|t('commerce') }}</button>
</form>
```

There are several things to note:

* Subscribing a user to a plan requires that the user have a stored payment source. If a user does not have one, you can add it by displaying the payment form.
* If you wish to set subscription parameters, such as amount of trial days, it is strongly recommended to make use of the [subscription events](events.md#the-beforecreatesubscription-event) instead of POST data.
* When using Stripe, it is not possible to choose which payment source to use, if multiple are saved. Instead, Stripe will use the default payment source associated with that customer.

## Canceling the subscription

To cancel a subscription you can use the following template that assumes that the `subscription` variable is available and set to an instance of `craft\commerce\elements\Subscription`.

```twig
<form method="POST">
    <input type="hidden" name="action" value="commerce/subscriptions/cancel">
    <input type="hidden" name="subscriptionUid" value="{{ subscription.uid|hash }}" />
    {{ redirectInput('shop/services') }}
    {{ csrfInput() }}

    {{ subscription.plan.getGateway().getCancelSubscriptionFormHtml()|raw }}

    <button type="submit">{{ "Unsubscribe"|t('commerce') }}</button>
</form>
```

If you wish to set cancellation parameters, it is strongly recommended to make use of the [subscription events](events.md#the-beforecancelsubscription-event) instead of POST data.

## Switching the subscription plan

To switch a subscription plan you can use the following template that assumes that the `subscription` variable is available and set to an instance of `craft\commerce\elements\Subscription`.

```twig
{% for plan in subscription.alternativePlans %}
    <div><strong>Switch to {{ plan.name }}</strong></div>
    <form method="POST">
        <input type="hidden" name="action" value="commerce/subscriptions/switch">
        <input type="hidden" name="planUid" value="{{ plan.uid|hash }}">
        <input type="hidden" name="subscriptionUid" value="{{ subscription.uid|hash }}">
        {{ csrfInput() }}

        {{ plan.gateway.getSwitchPlansFormHtml(subscription.plan, plan)|raw }}
        <button type="submit" class="button link">{{ "Switch"|t('commerce') }}</button>
    </form>
    <hr />
{% endfor %}
```

If you wish to set parameters for switching the subscription plan, it is strongly recommended to make use of the [subscription events](events.md#the-beforeswitchsubscriptionplan-event) instead of POST data.

## Reactivating a canceled subscription

To reactivate a subscription plan you can use the following template that assumes that the `subscription` variable is available and set to an instance of `craft\commerce\elements\Subscription`.

Note, that not all canceled subscriptions might be available for reactivation, so make sure to check for that.

```twig
{% if subscription.canReactivate() %}
    <form method="POST">
        <input type="hidden" name="action" value="commerce/subscriptions/reactivate">
        <input type="hidden" name="subscriptionUid" value="{{ subscription.uid|hash }}">
        {{ csrfInput() }}

        <button type="submit" class="button link">{{ "Reactivate"|t('commerce') }}</button>
    </form>
{% endif %}
```
