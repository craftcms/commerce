# Shipping Methods

In Craft Commerce it is possible to define your own Shipping Methods within a plugin.

The plugin would provide Shipping Methods the customer can choose from. In addition, each Shipping method would need one or more shipping rule which allows the Shipping Method to 'match' the order and be available for the current order, as well as provide the associated shipping costs.

You can provide one or more shipping methods with the following method in their main plugin class:

```
    public function commerce_registerShippingMethods()
    {
        return [new CustomShipper_CourierDeliveryMethod()];
    }
``` 

The plugin can provide one or more shipping method classes with the following interface:


# Shipping Method Interface

The shipping method interface requires a class with the following methods:

### getType()
Returns the type of Shipping Method. This might be the name of the plugin or provider.
The core shipping methods have type: `Custom`. This is shown in the control panel only.

### getId()
This must return null.

### getName()

Returns the name of this Shipping Method as displayed to the customer and in the control panel.

### getHandle()

This is the handle added to the order for the chosen shipping method. 

### getCpEditUrl()

Returns a control panel URL to a place where you can configure this shipping method's rules.  
Return an empty string if the method has no link.

### getRules()

Returns an array of rules that meet the `ShippingRules` interface. (see below)

### getIsEnabled()

Is this shipping method available to the customer to select.


# Shipping Rules Interface

A shipping method returns an array of rules objects. The shipping engine goes through each rule and calls `matchOrder()`. It expects a `true` or `false` returned if this shipping method can be applied to the order/cart. The matched rule also returns the costs to the cart if the rule matches. 

These are the methods required for the shipping rule objects:


### getHandle();

Returns the unique handle of this Shipping Rule

### matchOrder(\Craft\Commerce_OrderModel $order)

Return a boolean.
Is this rule a match on the order? If false is returned, the shipping engine tries the next rule on the current shipping method. If all rules return false, the shipping method is not available for selection by the customer on the order.

### getIsEnabled()

Is this shipping rule enabled, if not, the matchOrder() is not attempted.

### getOptions();

Stores this data as json on the orders shipping adjustment. For example you might include all data used to determine the rule matched.

### getPercentageRate()

Returns the percentage rate that is multiplied per line item subtotal.  
Zero will not make any changes

### getPerItemRate()

Returns the flat rate that is multiplied per qty.  
Zero will not make any changes.

### getWeightRate()

Returns the rate that is multiplied by the line item's weight.  
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
