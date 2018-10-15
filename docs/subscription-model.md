# Subscription Model
Whenever you’re dealing with a subscription in your template, you’re actually working with an `\craft\commerce\elements\Subscription` object.

## Simple Output

Outputting a `\craft\commerce\elements\Subscription` object in your template without attaching a property or method will print out the string `Subscription to “{plan}”`, where `plan` is the name of the [subscription plan](/en/plan-model.md).
	
# Attributes and methods

Subscription model has the following attributes and methods:

## Attributes

### id

The element id of the subscription.

### userId

The id of the user that has the subscription.

### planId

The id of the subscription plan.

### gatewayId

The id of the gateway for the subscription.

### orderId

The id of the order that contains this subscription (if any).

### reference

The subscription reference on the gateway.

### nextPaymentDate

The date of next expected payment.

### subscriptionData

The subscription data from the gateway.

### isCanceled

Whether this subscription is canceled.

### dateCanceled

Date when the subscription was canceled.

### isExpired

Whether this subscription is expired

### dateExpired

Date when this subscription expired

## Methods

### canReactivate()

Whether this subscription has been canceled and can be reactivated.

### getIsOnTrial()

Whether this subscription is on a trial currently.

### getGateway()

Return the gateway for the subscription.

### getPlan()

Return the plan for the subscription.

### getName()

Returns the string `Subscription to “{plan}”`, where `plan` is the name of the [subscription plan](/en/plan-model.md).

### getAlternativePlans()

Returns an array of alternative [subscription plans](/en/plan-model.md) available for this subscription.

### getPlan()

Return the plan for the subscription.

### getPlanName()

Return the name for the subscription's plan.

### getAllPayments()

Return an array of all the [payments](/en/subscription-payment-model.md) made for this subscription.

### getSubscriber()

Return the subscriber for this subscription.

### getTrialExpires()

Return the date and time for when the trial expires.
