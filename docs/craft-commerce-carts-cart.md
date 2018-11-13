# craft.commerce.carts.cart

This template function returns the current user’s cart (a <api:craft\commerce\elements\Order> object).

You would get cart for the current user like this:

```
{% set cart = craft.commerce.carts.cart %}
```
which is the same as:
```
{% set cart = craft.commerce.getCarts().getCart()%}
```

You can now show the current customer their cart, and add things to this cart with forms in your templates.

Once a cart is completed and turned into an order, calling the function again will give
your user a fresh new cart.
