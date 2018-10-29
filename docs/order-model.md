# Order Model
Whenever you’re dealing with an order or cart in your template, you’re actually working with an `\craft\commerce\elements\Order` object.

## Simple Output

Outputting an `\craft\commerce\elements\Order` object in your template without attaching a property or method will return the order’s [short number](#shortnumber):

```
Order Number: {{ order }}
```

# Overview

Order models are both carts and orders. They are the same thing. A cart just has it’s `isCompleted` property set to false.

Usually you will be working with an order model in your template in 2 situations.

1. When you are working with the active cart of the current customer.
```
{% set cart = craft.commerce.carts.cart %}
```

2. When working with completed orders.
```
{% set pastOrders = craft.orders.customer(craft.commerce.customer).isCompleted(true).all() %}`
{% for order in pastOrders %}
	Order Number: {{ order.shortNumber }}<br>
	Order Total: {{ order.totalPrice }}<br><br>
{% endfor %}	
```
	
Order Model's have the following attributes and methods:

## Attributes

### id

The element id of the order.

### number

The unique identifier of the order, the customer will see this, and is the best thing to use in urls.

### shortNumber

The first 7 characters of the unique `number` identifier of the order.

### couponCode

The current coupon code applied to the cart

### totalQty

The total number of items in the order. For example an order might have 2 variants (line items) but each have a quantity of 2, this `totalQty` would be 4.
Alias of `getTotalQty()`

### totalWeight

Total weight of all items on the order summed.

### itemSubtotal

Sum of all the item’s `subtotal`. (Item subtotal does not any adjustments made to the line items)

### itemTotal

Sum of all the items totals. (Includes all adjustments made to line items)

### adjustmentsTotal

Total of all adjustments made to line items and the order. (Does not include adjustments marked as 'included')


### getAdjustmentsTotalByType(type, included)

Gets the total of all adjustments of that type made to the line items and order.

Included types are `tax`, `shipping`, `discount`.

The included param is optional and defaults to false.

Included adjustments don't affect the price of the order, and are there for information only.

Examples:

```
{{ order.getAdjustmentsTotalByType('discount') }} // returns the total of all discounts

{{ order.getAdjustmentsTotalByType('tax', true) // returns the total of taxes included in the price

{{ order.getAdjustmentsTotalByType('tax') // returns the total of taxes added to the price of the order

{{ order.getAdjustmentsTotalByType('shipping') // returns the total of shipping adjustments

{{ order.getAdjustmentsTotalByType('customAdjuster') // returns the total of a adjustment created by a custom adjuster 

```

### totalPrice

Total order price, including all items and adjustments.

### totalPaid

Total amount paid on to the order.

### email

Currently set email address on the order.

### dateOrdered

The date the orders default status was set and the cart was turned into an order.

### datePaid

The date the order was paid.

### billingAddressId

The id of the linked billing address. You can get the billing address model with the `billingAddress` attribute.

### billingAddress

This returns the address model from the database for the current `billingAddressId` OR the caches address if the order is complete.

### shippingAddressId

The id of the linked shipping address. You can get the shipping address model with the `shippingAddress` attribute.

### shippingAddress

This returns the address model from the database for the current `shippingAddressId` OR the caches address if the order is complete.

### shippingMethodHandle

The handle of the currently applied shipping method. You can get the set shipping method's model with the `shippingMethod` attribute.

### gatewayId

The ID of the linked gateway. You can get the set gateway's model with the `gateway` attribute.

### currency

The currency the order will use for payments.

### lastIp

The last IP address used to update the order on the front end before completedAt is set.

### customerId

The customer record for this order. You can also use `customer` to get the customer model which can tell you which craft user 
made the order, as well as access the addresses for this customer.

### lineItems

The line item models as an array on this order.

### adjustments

The order adjustments models on this order.

### transactions

The transactions on this order.

### message

The current message saved when the current order status was set.

## Methods

### getPdfUrl($option = '')

Returns the url to the pdf for this order. Requires that your general settings point to a valid html template file.  
Optionally can pass an 'option' string to this method to identify the type of pdf you want to the template.
The order PDF template is passed an `order` and `option` variable.

### getShippingmethod()

Returns a `Commerce_ShippingMethodModel` with the current shipping method for the model, or `null` if none exists.

### isEmpty()

Is the current order or cart empty.

### isPaid()

Are the payments made to the order totalling equal or greater value than the `totalPrice`

### outstandingBalance()

The amount owing on the order to reach the totalPrice.
