# Customer Info

## General 

The Customer Info field allows you to add Commerce related customer information to the Craft user profile's field layout. This allows you to see the past orders for a customer as well as all addresses they have in their address book.

<img src="assets/customer-info-field.png" width="600" alt="Product Field Modal.">

## Templating

If you named your customer info field `customerInfo` you would be able to have a {entry:343:link} returned for a user like so:

```twig
{% set customer = user.customerInfo %}
```

Example of getting the customers addresses:

```twig
{% for address in user.customerInfo.addresses %}
	{{ address.firstName}} <br>
{% endfor %}
```

Example of getting the customers past orders:

```twig
{% for order in user.customerInfo.orders %}
	{{ order.number}} <br>
{% endfor %}
```
