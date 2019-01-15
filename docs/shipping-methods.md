# Shipping Methods

Shipping methods are only available in the Pro edition of Craft Commerce. This page only applies to developers using the Pro edition of Craft Commerce.

If you need to add shipping costs to the cart, you have the following options:

1) Use the built in shipping method and shipping rules engine to define your rules and prices based on a few product and cart attributes, including per item rates, base order rates, weight rates, and percentage of cost rates.
This engine is fairly powerful and can meet the needs of most small businesses with simple to medium complex shipping needs.

2) Write a plugin or module that provides your own shipping method. This allows you to present more than one option to the customer, and writing your own shipping method allows you to use the option (1) above at the same time. Your shipping rules could use any external API to look up prices, or you could just build the pricing logic out in PHP.

3) Write an order adjuster class. Going this route mean you likely have shipping costs you can’t codify in the native shipping engine UI AND you never need to offer a shipping method choice to your customers between the native shipping engine methods your custom logic pricing. Use this when you will automatically add dynamically calculated shipping costs to the cart.

Below is a guide on writing a plugin or module supplied shipping method (2), and also an example of a custom adjuster (3) to add shipping costs.

## Shipping Method Interface

The shipping method interface requires a class with the following methods:

### getType()
Returns the type of shipping method. This would likely be the handle of your plugin.

### getId()

This must return null.

### getName()

Returns the name of this shipping method as displayed to the customer and in the Control Panel.

### getHandle()

This is the handle added to the order when a customer selects this shipping method.

### getCpEditUrl()

Returns a Control Panel URL to a place where you can configure this shipping method’s rules.
Return an empty string if the method has no link.

### getRules()

Returns an array of rules that meet the `ShippingRules` interface. (see below)

### getIsEnabled()

Is this shipping method available to the customer to select.

## Shipping Rules Interface

A shipping method returns an array of rules objects. The shipping engine goes through each rule one by one and calls `matchOrder()`. It expects a `true` or `false` returned if this shipping method can be applied to the order/cart. The first matched rule returns the costs to the cart.

These are the methods required for the shipping rule objects:

### getHandle();

Returns the unique handle of this shipping rule

### matchOrder(\Craft\Commerce_OrderModel $order)

Return a boolean.
Is this rule a match on the order? If false is returned, the shipping engine tries the next rule on the current shipping method. If all rules return false, the shipping method is not available for selection by the customer on the order.

### getIsEnabled()

Is this shipping rule enabled, if not, the matchOrder() is not attempted.

### getOptions();

Stores this data as JSON on the order’s shipping adjustment. For example, you might include all data used to determine the rule matched.

### getPercentageRate()

Returns the percentage rate that is multiplied per line item subtotal.
Zero will not make any changes

### getPerItemRate()

Returns the flat rate that is multiplied per qty.
Zero will not make any changes.

### getWeightRate()

Returns the rate that is multiplied by the line item’s weight.
Zero will not make any changes.

### getBaseRate()

Returns a base shipping cost. This is added at the order level.
Zero will not make any changes.

### getMaxRate()

Returns a max cost this rule should ever apply.
If the total of your rates as applied to the order are greater than this, the baseShippingCost on the order is modified to meet this max rate.

### getMinRate()

Returns a min cost this rule should have applied.
If the total of your rates as applied to the order are less than this, the baseShippingCost on the order is modified to meet this min rate.
Zero will not make any changes.

### getDescription()

Returns a readable description of the rates applied by this rule.

## Registering your Shipping Method and Rules

Once you have created your shipping method class and its associated shipping rules classes, you need to register your shipping method class instance by using the `EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS` event in your module or plugin’s `init()` method.

Here is an example of doing so:

```php
Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) {
    $event->shippingMethods[] = new MyShippingMethod();
});
```

## Shipping Adjuster

If you decide not to make a shipping method, you could just make a custom adjuster to add shipping costs to the cart.

To learn how to create an adjuster see [Adjusters](adjusters.md), and simply set the `type` of the adjuster to `shipping`.
