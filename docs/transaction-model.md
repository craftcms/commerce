# Transaction Model

Transactions record the payment history of an order.

A transaction can be of a certain type and status, and contain information relevant to the payment and communication to the third party payment gateway.

### id

The Commerce ID of the transaction.

### hash

The unique hash identifier of the transaction as created by Craft Commerce.

### type

The type of transaction. Possible values are:

`purchase` The transaction represents a purchase or immediate payment request. If this transaction type succeeds, the charge on the gateway took the funds from the customers credit card immediately and payment has been made.

`authorize` The transaction represents an authorization of a payment with the gateway. If successful, the payment was successfully authorized, but an additional capture action needs to take place for the funds to be taken from the credit card.

`capture` This transaction represents a capture of a previous `authorize` transaction. If this transaction type succeeds, the charge on the gateway took the funds from the customers credit card and payment has been made. This transaction is always the child of an authorize transaction.

`refund` This transaction represents a refund of a payment. It is always the child transaction of either a `purchase` or `capture` transaction. You can not refund an authorization.

### amount

The amount of the transaction. This amount is in the primary currency.

### paymentAmount

The payment amount of the transaction, which is the amount sent and used when communicating with the payment gateway. This amount is in the currency of the order’s payment currency.

### paymentRate

This stores the currency conversion rate of the order’s payment currency at the time payment was attempted.

### status

The status of the transaction. Possible values are:

`pending` The transaction is pending getting a `redirect`, `success` or `failed` status.

`redirect` The initial transaction was registered successfully with the offsite gateway, and we have been told to redirect to the offsite gateway. This will be the status while the customer is on the gateways offsite page.

`success` The transaction is successful.

`failed` The transaction failed. See the transaction `code` and `message` to find out more information.

### reference

The reference of the transaction as defined by the gateway.

### message

The plain text message response from the gateway. Usually a sentence. This message is used to show to the customer if the transaction failed.

### response

The full response data from the gateway, serialized as JSON. Useful for debugging.

### code

The response code from the gateway. This will usually align in its meaning with the `message`.

### parentId

Some transactions are children of another transaction. For example, capture transactions are children of authorize transactions, and refund transactions are children of capture or purchase transactions.

### order

The [Order model](order-model.md) this transaction belongs to.

### orderId

The order ID of the [Order model](order-model.md) this transaction belongs to.

### paymentMethodId

The ID of the payment method used for communicating with the third party gateway.

