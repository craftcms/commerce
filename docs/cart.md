# Cart

A cart is just an [Order](en/orders.md) that has not yet been completed. A customer can edit the contents of a cart, but once the cart becomes an order it is no longer editable by the customer.

You can view carts in the 'Orders' section of the control panel. You can also view Active Carts
which are carts that have been updated in the last 24 hours, and inactive carts older than 24 hours
and likely to become abandoned.

You can set the system to purge (delete) abandoned carts after a given time period in [your config](en/general-config.md#purgeinactivecartsduration), the default of which is 3 months.


In your templates, you can get the current user's cart with {entry:125:link}:
```twig
{% set cart = craft.commerce.getCart() %}
```

The above code will generate a new cart in the session if none exists. Its likely you would only 
want to make this assignment once per page request, but more than once does not affect performance.

To see how to use the cart in templates look at the [order mode](en/order-model) documentation.
