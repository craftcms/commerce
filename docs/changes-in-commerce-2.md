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
| `craft.commerce.statesArray`              | `craft.commerce.states.statesAsList`                      | D      
| `craft.commerce.taxRates`                 | `craft.commerce.taxRates.allTaxRates`                     | BC     
| `craft.commerce.taxZones`                 | `craft.commerce.taxZones.allTaxZones`                     | BC   


### Model Changes

#### Purchasables (like variants).

| Old                                       | New                                                       | Change 
|-------------------------------------------|-----------------------------------------------------------|--------
| `purchasable.purchasableId`               | `purchasable.id`                                          | BC   

### Updating the cart

In Commerce 2, there has been a change to how the update cart controller action responds, it now returns a `cart` variable from all cart controller actions for both success and failure.

Previously you needed to retrieve the cart with:

```
{% set cart = craft.commerce.cart %}
```

This cart would have any errors applied, but there was no easy way to access the original cart before the errors.

If any part of the update params fails, no changes will be made to the cart, and the cart in it's errored state will be returned to the template.

Thus for Commerce 2 we recommend doing something like this:


```
{% if cart is not defined %}
{% set cart = craft.commerce.getCarts().getCart() %} // Gets the clean (no errors) cart from the session/db
{% endif %}
```

This allows you to use the cart returned from the update cart actions (with its errors), or a known good cart.

Previously when updating 2 different things on the cart, one could fail and the other one succeed. Now the update cart action will only fully succeed or fail. There are no partially updated cart.
