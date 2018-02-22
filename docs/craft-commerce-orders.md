# craft.commerce.orders 
## How to get orders

You can access your site’s orders from your templates via `craft.commerce.orders`
It returns an [ElementCriteriaModel](http://buildwithcraft.com/docs/templating/elementcriteriamodel) object.

```twig
{% set orders = craft.commerce.orders.find() %}

{% for order in orders %}
    {{ order.number }} - {{ order.totalPrice}} <br>
{% endfor %}
```

## Parameters

`craft.commerce.orders` supports the following parameters:

### type
Product Type model or handle.

### typeId
Product type id.

### number
The unique hash of the order.

### completed
Accepts `true`.  e.g ```{% set orders = craft.commerce.orders.completed(true).find() %}``` would 
return completed orders since they have `isCompleted` set to true.

### isCompleted
Accepts `1` or `not 1`.  e.g ```{% set orders = craft.commerce.orders.isCompleted('not 1').find() %}``` would 
return incomplete orders (carts) since they have `isCompleted` set to false.

### dateOrdered
The date the order was completed.

### orderStatus
accepts an `orderStatus` model.

### orderStatusId
Accepts the id of an Order Status.

### customer
A customer Model can be passed to get orders for that customer only. e.g `{% set orders = craft.commerce.orders.customer(craft.commerce.customer).find() %}`
Do not use this to get a cart, as the default response does not include orders that are still 
carts (use `{% set cart = craft.commerce.getCart %}` to get the current user's cart).

### user
A customer Model can be passed to get orders for that user only. e.g `{% set orders = craft.commerce.orders.user(currentUser).find() %}`
Do not use this to get a cart, as the default response does not include orders that are still 
carts (use `{% set cart = craft.commerce.getCart %}` to get the current user's cart).

### customerId
Accepts an id of a customer.

### updatedAfter
Only fetch orders with an Updated Date that is on or after the given date.

You can specify dates in the following formats:

- YYYY
- YYYY-MM
- YYYY-MM-DD
- YYYY-MM-DD HH:MM
- YYYY-MM-DD HH:MM:SS
- A Unix timestamp
- A DateTime variable

### updatedBefore

Only fetch orders with an Updated Date that is before the given date.

You can specify dates in the following formats:

- YYYY
- YYYY-MM
- YYYY-MM-DD
- YYYY-MM-DD HH:MM
- YYYY-MM-DD HH:MM:SS
- A Unix timestamp
- A DateTime variable

### isPaid

Accepts `true`. Limits results to only orders where totalPaid is >= totalPrice

### isUnPaid

Accepts `true`. Limits results to only orders where totalPaid is < totalPrice

### datePaid

The date the order was paid.

### hasPurchasables
Returns orders that contains specific purchasables.

Accepts: An array of models meeting the Purchasable interface (like variants) OR an array of Purchasable Element IDs
 
For example:

```twig
{% if currentUser %}
    {% set order = craft.commerce.orders.user(currentUser).hasPurchasables([product.defaultVariant]).first() %}
    {% if order %}
        I already own this product:  <a href="shop/order?orderNumber={{ order.number }}">Order #{{ order.shortNumber }}</a>
    {% endif %}
{% endif %}
```

or

```twig
{% set  orders = craft.commerce.orders({
  hasPurchasables: [32,34,35]
}) %}
```
