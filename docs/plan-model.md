# Plan Model
Whenever you’re dealing with a subscription plan in your template, you’re actually working with an `\craft\commerce\base\Plan` object.

## Simple Output

Outputting a `\craft\commerce\base\Plan` object in your template without attaching a property or method will print out the name of the plan.
	
# Attributes and methods

Plan model the following attributes and methods:

## Attributes

### id

The plan id.

### gatewayId

The id of the gateway for the plan.

### name

The name of the subscription plan.

### handle

The handle of the subscrip.

### reference

The plan reference on the gateway.

### enabled

Whether the subscription plan is enabled.

### isArchived

Whether the subscription plan is archived.

### dateArchived

Date when the plan was archived.

### planData

The plan data as sent by gateway.

### uid

The plan uid.

## Methods

### getGateway()

Returns the gateway for this subscription plan.

### getPlanData()

Returns the stored plan data as returned by the gateway.

### getSubscriptionCount(int $userId)

Return the subscription count for the plan.

### hasActiveSubscription(int $userId)

Whether the subscription plan has any active subscriptions for the user.

### getActiveUserSubscriptions(int $userId)

Returns all active user subscriptions for this plan.

### canSwitchFrom(Plan $currentPlan)

Whether it's possible to switch to this plan from a different plan.
