# Order Status Model

Order Statuses are the custom statuses set up in your Commerce settings. They are not the standard status found on elements, but custom statuses an order can go through after a user completes a cart.

OrderStatusModels have the following attributes and methods:

## Attributes

### id

The status ID.

### name

The name of the order status.

### handle

The handle of the order status.

### color

The name of the color of the order status

### default

Returns `true` or `false` depending on if this status is the default applied to new orders.
