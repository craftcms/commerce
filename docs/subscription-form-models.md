# Subsription Models

## Subscription form models

As with [payment form models](/en/payment-form-models.md), subscription form models are used to validate different subscription parameters and to pass them to the gateway.

Since there are more actions available for subscriptions, there are also more of them, however, due to the differing nature of how gateways handle subscriptions, they are also bound to have more differences between gateways.

### `craft\commerce\models\subscriptions\SubscriptionForm` model

This model is used when starting a new subscription. This is also only of the models to have a parameter defined by Commerce, instead of a gateway.

#### The `trialDays` attribute

The number of trial days to afford the user when subscribing.

Validation: if present, must be a positive integer

### The `craft\commerce\models\subscriptions\CancelSubscriptionForm` model

This model is used when canceling a subscription. Commerce does not add any parameters, but make sure to check the documentation for the plugin that is providing the gateway.

### The `craft\commerce\models\subscriptions\SwitchPlansForm` model

This model is used when switching a subscription between subscription plans. Commerce does not add any parameters, but make sure to check the documentation for the plugin that is providing the gateway.

## Other subscription models

### The `craft\commerce\models\subscriptions\SubscriptionPayment` model

This model, unlike the form models, is not used to pass any information or parameters to the payment gateway. Instead, it is used by the payment gateway to return standardized information about a subscription payment.

#### The `paymentAmount` attribute

The payment amount

#### The `paymentCurrency` attribute

The currency for the payment

#### The `paymentDate` attribute

Date of payment

#### The `paymentReference` attribute

THe subscription payment reference on the payment gateway

#### The `paid` attribute

Whether the subscription payment was paid in full

#### The `forgiven` attribute

Whether the subscription payment was forgiven

#### The `response` attribute

Full gateway response about the subscription payment

