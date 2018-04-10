# Order Adjustment Model 

Order Adjustments belong to an Order and contain information about an adjustment made to the cart. The standard adjustment types are 'Shipping', 'Discount', and 'Tax'. You can access all adjustments on the order in your templates with `cart.adjustments`, and you can access all adjustments made to a line item with `lineItem.adjustments`.

### name

The name of the adjustment.

### description

The description of the adjustment. For example, a shipping adjustment might explain the shipping costs in a sentence.

### amount

The total amount of costs added or removed from the order. This is not used in order totalling, this number represents the changes made to the line items or order totals.

### optionsJson

A json encoded array of information the adjustment used to perform its adjustment to the order. For example, all the shipping rules and amounts used to add shipping cost to the cart.

### orderId

The ID of the order this adjustment applied its adjustments to.

