# Subscriptions

Commerce 2 supports subscriptions as well as multiple concurrent subscriptions.

Subscriptions are handled by gateways - it’s not possible to subscribe a user to a Commerce subscription plan that the payment gateway does not know.

While some gateways allow creating subscription plans via API, some of them don’t, so to keep future options open, Commerce does not allow creating new subscription plans on the gateway. However, it’s still possible to write a plugin that does that.

To set up a subscription plan, you must set up a payment gateway that supports subscriptions, then go to Commerce → Settings → Subscription Plans to set up subscription plans.

## Subscription support across gateways

Currently, only `craftcms/commerce-stripe` gateway supports subscriptions. If you need subscriptions for another gateway that supports them, a plugin first must be created that implements that gateway.

## Subscription statuses

Commerce has the following subscription statuses:

- `active` - if a subscription is within a paid billing cycle, it is considered active, even if it’s set to cancel at the end of the current billing cycle.
- `canceled` - if a subscription is outside of a paid billing cycle, it is considered to be canceled, if it was canceled by the user.
- `expired` - if a subscription is outside of a paid billing cycle, it is considered to be expired if has been marked as such by the gateway, either because it was set to a fixed amount of billing cycles or a payment failed.
- `trial` - if a subscription is within the set amount of trial days from the beginning of the subscription.

In case more than one subscription status could be applied, the order of precedence is `expired`, `canceled`, `trial` and `active`.

## Subscribing

You create a subscription by subscribing a user to a subscription plan. As you are subscribing a user, it is possible to pass parameters for the subscription. All subscription gateways are expected to at the very least support a `trialDays` parameter.

## Changing a subscription’s plan

Depending on the gateway, it might be possible to switch subscription plans. Please consult the gateway plugin’s documentation to see if this is the case.

## Canceling a subscription

Depending on the gateway, canceling subscriptions supports different options. Please consult the gateway plugin’s documentation to see if your particular gateway supports this.

## Deleting subscriptions or plans

### Gateways

It depends on the gateway, whether it is permitted to delete a subscription plan. Some gateways allow this and preserve all existing subscriptions, while others might not allow that. However, it is never recommended to delete a subscription plan on the gateway, as it might incur the loss of historical data.

### Commerce

As far as Commerce is concerned, it will attempt to delete all local subscription plans, when a gateway is deleted. If a subscription (even expired) exists for a subscription plan, this action will be prevented. Likewise, Commerce will prevent deleting a user that has active or expired subscriptions.
