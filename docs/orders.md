# Orders

An order is a completed cart.

Carts are really just an [order model](order-model.md) that is being built by customers on the front end of your website.

When a customer completes a cart or makes payment it becomes an Order and gets a `dateOrdered` date set, the `isCompleted` set to true, as well as a default [Order Status](custom-order-statuses.md) set.

You can view orders in the 'Commerce > Orders' section of the control panel.
