# Cart

A cart is an [order](orders.md) that has not yet been completed. A customer can edit the contents of a cart, but once the cart becomes an order it is no longer editable by the customer.

You can view all carts on the Orders index page. You can also only view _active_ carts (carts that have been updated in the last 24 hours) and _inactive_ carts (carts that are older than 24 hours and likely to be abandoned).

You can set the system to purge (delete) abandoned carts after a given time period in [your config](configuration.md), the default of which is 3 months.

In your templates, you can get the current user’s cart with [craft.commerce.carts.cart](craft-commerce-carts-cart.md).

```twig
{% set cart = craft.commerce.carts.cart %}
```

The above code will generate a new cart in the session if none exists. It’s likely you would only
want to make this assignment once per page request, but more than once does not affect performance.

To see how to get cart information in templates look at the <api:craft\commerce\elements\Order> class reference.
