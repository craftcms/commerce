# craft.commerce.carts.cart

This template function returns the current users cart, which is an [Order Model](order-model.md).

You would get cart for the current user like this:

```
{% set cart = craft.commerce.carts.cart %}
```
which is the same as:
```
{% set cart = craft.commerce.getCarts().getCart()%}
```

You can now show the current customer their cart, and add things to this cart with forms in your templates.

Once a Cart is completed and turned into an Order, calling the function again will give
your user a fresh new cart.
