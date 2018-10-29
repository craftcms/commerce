# Order History Model

An order history model records the order status changes an order goes through. An order can have many history models related to it, the latest history model represents the current status an order is in.

To get all history models for an order with twig you could do:

```twig
Current Order Status: {{order.orderStatus.name}} <br>
{% for history in order.histories %}
  Status: '{{ history.newStatus.name ?? ""}}' (previously '{{ history.prevStatus.name ?? ""}}')
  with message: '{{ history.message }}'<br>
{% endfor %}

```

## message

The message saved when the order status was changed.

## orderId

The order ID.

## prevStatus

Returns an [Order Status model](order-status-model.md) of the previous status this order was in.

Returns `null` if there was no previous status, as is the case when a new order is created.

## prevStatusId

The the ID of the previous status this order was in.

## newStatus

Returns an [Order Status model](order-status-model.md) of the new status this order is in.

## newStatusId

The status this order history model recorded

## customerId

The customer who made this order ID
