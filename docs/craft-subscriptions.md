# craft.subscriptions

## How to get subscription

You can retrieve subscriptions like entries and products as they are also elements within Craft.

You can access your site’s subscriptions from your templates via `craft.subscriptions`
It returns an [ElementQuery](https://github.com/craftcms/docs/blob/v3/en/element-queries.md) object.

```twig
{% set subscription = craft.subscriptions.reference('sub_CTnhYZOTr4zkwW').one() %}
{% if subscription %}
{{ subscription.plan.name }} - {{ subscription.status }} - {{ subscription.nextPaymentDate|date }}
{% endif %}
```

## Parameters

`craft.subscriptions` supports the following parameters:

### ID
The subscription's element ID.

Accepts: Integer

### dateCancelled
The date the subscription was cancelled.

Accepts: Date|string

### dateExpired
The date the subscription was cancelled.

Accepts: Date|string

### gatewayId
The gateway ID this subscription belongs to.

Accepts: Integer

### isCancelled
Whether the subscription status is cancelled

Accepts: boolean (`true` or `false`)

### isExpired
Whether the subscription status is expires

Accepts: boolean (`true` or `false`)

### nextPaymentDate
The date of the next payment due for this subscription

Accepts: Date

### onTrial
Whether the subscription is currently on trial.

Accepts: boolean (`true` or `false`)

### plan
The plan the subscription beloings to.

Accepts: Plan

### planId
The ID of the plan the subscription beloings to.

Accepts: Integer

### reference
The reference for this subscription within the gateway's 3rd party system.

Accepts: string

### subscribedAfter
Get subscriptions subscribed after this date

Accepts: Date

### subscribedBefore
Get subscriptions subscribed before this date

Accepts: Date

### trialDays
Get subscriptions by the number of trial days given

Accepts: Integer

### user
The user the subscription belongs to

Accepts: User

### userId
The user ID the subscription belongs to

Accepts: Integer
