# Subscription Payment Model

Subscription payment model represents a single payment made for a subscription. The payment models can only be acquired by calling the [appropriate method on the subscription model](subscription-model.md#getallpayments), so they don’t include a reference to the subscription itself.

## Attributes

Subscription payment model the following attributes and methods:

### paymentAmount

The amount of the payment.

### paymentCurrency

The currency of the payment.

### paymentDate

The date of the payment.

### paymentReference

The payment reference on the gateway.

### paid

Whether the payment was collected.

### forgiven

Whether the payment was forgiven.

### response

The gateway information about the payment.
