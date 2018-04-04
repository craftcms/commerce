# Changes in Commerce 2

## Twig template changes

### Services available in Twig

The commerce variable class is now gone, and not loaded into the `craft.commerce` variable anymore. Instead `craft.commerce` now returns the Commerce plugin instance.

All Commerce service methods can now be accessed through the plugin instance.

Example:

`craft.commerce.paymentCurrencies` will return the PaymentCurrencies service class.  
`craft.commerce.paymentCurrencies.getAllPaymentCurrencies()` will call the method on the class to return all payment currency models.
 
 ### Element queries 

The element queries now exist on the root craft variable.

Example:

To get all produces in Commerce 1.2.x:  
`craft.commerce.products.find()`  

To do the same in Commerce 2:  
`craft.products.all()`


### Changes

Deprecation of variables was preferred, but breaking changes could not be avoided due to naming collisions between the service names and the previous variable name.

Use the table below to update your twig templates.  
D - Deprecated  
BC - Breaking Change  

| Old                                       | New                                                       | Change 
|-------------------------------------------|-----------------------------------------------------------|--------
| `craft.commerce.products`                 | `craft.products`                                          | BC     
| `craft.commerce.variants`                 | `craft.variants`                                          | BC     
| `craft.commerce.orders`                   | `craft.orders`                                            | BC     
| `craft.commerce.cart`                     | `craft.commerce.carts.cart`                               | D      
| `craft.commerce.availableShippingMethods` | `craft.commerce.carts.cart.availableShippingMethods`      | D      
| `craft.commerce.countries`                | `craft.commerce.countries.allCountries`                   | BC     
| `craft.commerce.countriesList`            | `craft.commerce.countries.allCountriesAsList`             | D      
| `craft.commerce.currencies`               | `craft.commerce.currencies.allCurrencies`                 | BC     
| `craft.commerce.customer`                 | `craft.commerce.customers.customer`                       | D      
| `craft.commerce.discountByCode`           | `craft.commerce.discounts.discountByCode`                 | D      
| `craft.commerce.discounts`                | `craft.commerce.discounts.allDiscounts`                   | BC     
| `craft.commerce.paymentMethods`           | `craft.commerce.gateways.allCustomerEnabledGateways`      | BC     
| `craft.commerce.orderStatuses`            | `craft.commerce.orderStatuses.allOrderStatuses`           | BC     
| `craft.commerce.paymentCurrencies`        | `craft.commerce.paymentCurrencies.allPaymentCurrencies`   | BC     
| `craft.commerce.primaryPaymentCurrency`   | `craft.commerce.paymentCurrencies.primaryPaymentCurrency` | D      
| `craft.commerce.productTypes`             | `craft.commerce.productTypes.allProductTypes`             | BC     
| `craft.commerce.sales`                    | `craft.commerce.sales.allSales`                           | BC     
| `craft.commerce.shippingCategories`       | `craft.commerce.shippingCategories.allShippingCategories` | BC     
| `craft.commerce.taxCategories`            | `craft.commerce.taxCategories.allTaxCategories`           | BC     
| `craft.commerce.shippingMethods`          | `craft.commerce.shippingMethods.allShippingMethods`       | BC     
| `craft.commerce.shippingZones`            | `craft.commerce.shippingZones.allShippingZones`           | BC     
| `craft.commerce.states`                   | `craft.commerce.states.allStates`                         | BC     
| `craft.commerce.statesArray`              | `craft.commerce.states.allStatesAsList`                   | D      
| `craft.commerce.taxRates`                 | `craft.commerce.taxRates.allTaxRates`                     | BC     
| `craft.commerce.taxZones`                 | `craft.commerce.taxZones.allTaxZones`                     | BC   
| `customer.lastUsedBillingAddress`         | `customer.primaryBillingAddress`                          | BC  
| `customer.lastUsedShippingAddress`        | `customer.primaryShippingAddress`                         | BC  


### Model Changes

#### Purchasables (like variants).

| Old                                       | New                                                       | Change 
|-------------------------------------------|-----------------------------------------------------------|--------
| `purchasable.purchasableId`               | `purchasable.id`                                          | BC   

### Updating the cart

In Commerce 2, there has been a change to how the update cart controller action works. All cart actions now return a `cart` variable from all cart controller actions for both success and failure.

Now, if any part of the update fails, no changes will be saved to the cart, and the returned cart will have errors applied. Previously when updating 2 different things on the cart, one could fail and the other one could succeed. Now the update cart action will only fully succeed or fail. There are no partially applied updates to the cart.

Previously you needed to retrieve the cart with:

```
{% set cart = craft.commerce.cart %}
```

This cart would have any errors applied to its attributes, but there was no easy way to access the original cart before the errors. We were also limited to a single flash message with the first error.

Commerce 2 we recommend doing something like this:

```
{% if cart is not defined %}
  {% set cart = craft.commerce.getCarts().getCart() %} // Gets the clean (no errors) cart from the session/db
{% endif %}
```

This allows you to use the cart returned from the update cart actions (with its errors applied), or the last known good cart.

The changes mean a faster cart that reduces the number of database updates.

### Cart Validation

The cart (order) now places all errors on the fields that have the error, and also on the order/cart model with a error key to the location of the error.

For example, if an error is on the *second* line item, you can access the errors on the line item like this:

```
lineItem.getErrors()
```

this may return an error array like this: 

```
['qty' => 'Maximum quantity allowed is 3']
```

But in addition to this, the errors will also be on the order

```
order.getErrors()
```

will return an error array like this:

```
['lineItem[1].qty' => 'Maximum quantity allowed is 3']
```
