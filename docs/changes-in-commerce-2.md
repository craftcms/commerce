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

| Commerce 1                                | Commerce 2                                                | Change |
|-------------------------------------------|-----------------------------------------------------------|--------|
| `craft.commerce.products`                 | `craft.products`                                          | BC     |
| `craft.commerce.variants`                 | `craft.variants`                                          | BC     |
| `craft.commerce.orders`                   | `craft.orders`                                            | BC     |
| `craft.commerce.cart`                     | `craft.commerce.carts.cart`                               | D      |
| `craft.commerce.availableShippingMethods` | `craft.commerce.carts.cart.availableShippingMethods`      | D      |
| `craft.commerce.countries`                | `craft.commerce.countries.allCountries`                   | BC     |
| `craft.commerce.countriesList`            | `craft.commerce.countries.allCountriesAsList`             | D      |
| `craft.commerce.currencies`               | `craft.commerce.currencies.allCurrencies`                 | BC     |
| `craft.commerce.customer`                 | `craft.commerce.customers.customer`                       | D      |
| `craft.commerce.discountByCode`           | `craft.commerce.discounts.discountByCode`                 | D      |
| `craft.commerce.discounts`                | `craft.commerce.discounts.allDiscounts`                   | BC     |
| `craft.commerce.paymentMethods`           | `craft.commerce.gateways.allCustomerEnabledGateways`      | BC     |
| `craft.commerce.orderStatuses`            | `craft.commerce.orderStatuses.allOrderStatuses`           | BC     |
| `craft.commerce.paymentCurrencies`        | `craft.commerce.paymentCurrencies.allPaymentCurrencies`   | BC     |
| `craft.commerce.primaryPaymentCurrency`   | `craft.commerce.paymentCurrencies.primaryPaymentCurrency` | D      |
| `craft.commerce.productTypes`             | `craft.commerce.productTypes.allProductTypes`             | BC     |
| `craft.commerce.sales`                    | `craft.commerce.sales.allSales`                           | BC     |
| `craft.commerce.shippingCategories`       | `craft.commerce.shippingCategories.allShippingCategories` | BC     |
| `craft.commerce.taxCategories`            | `craft.commerce.taxCategories.allTaxCategories`           | BC     |
| `craft.commerce.shippingMethods`          | `craft.commerce.shippingMethods.allShippingMethods`       | BC     |
| `craft.commerce.shippingZones`            | `craft.commerce.shippingZones.allShippingZones`           | BC     |
| `craft.commerce.states`                   | `craft.commerce.states.allStates`                         | BC     |
| `craft.commerce.statesArray`              | `craft.commerce.states.statesAsList`                      | D      |
| `craft.commerce.taxRates`                 | `craft.commerce.taxRates.allTaxRates`                     | BC     |
| `craft.commerce.taxZones`                 | `craft.commerce.taxZones.allTaxZones`                     | BC     |