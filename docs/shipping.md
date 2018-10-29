# Shipping

The shipping system in Craft Commerce is a way for shipping costs to be added to the cart when a customer selects a shipping method you set up.

The core components of the shipping system are:

- Shipping categories
- Shipping zones
- Shipping methods and rules

Shipping methods and rules are at the core of the shipping engine. The shipping rules use shipping categories, shipping zones and additional order conditions to determine which shipping methods are available to the cart for your customer to select from.

## Shipping Categories

Shipping categories are a way to identify different types of products to the shipping system.

When defining a product type, you can choose the shipping categories that products of that type can belong to. When setting up individual products, an author can choose which shipping category that product belongs to.

While setting up shipping rules, you have the ability to include those shipping categories into the rule’s conditions, and costs.

For example, you might set up a shipping rule which should not be available if the cart contains a product from a particular shipping category. In additional, a shipping rule can also return special prices for different categories of products within the cart.

## Shipping Zones

Shipping zones are areas that you ship to. Shipping zones can be made up of one or more country, or one or more states within those countries.

For example, you might make a zone for USA & Canada, and a different zone for the international countries you ship to.

These zones can then be used within the shipping rules as conditions to match on the customers shipping address.

## Shipping Methods

Shipping methods are the choices made available to the customer during checkout. For example, a shipping method might be called “Pickup”, “FedEx”, “USPS”, or “Express”.

You can name these shipping methods anything that makes sense to the customer. They do not need to be shipping company names, but usually indicate the delivery method. For example, you could have 2 shipping methods, one called “FedEx Standard” and “FedEx Overnight”.

Shipping methods contain a collection of shipping rules. This rules contain conditions and pricing logic for their shipping method they belong to.

## Shipping Rules

Shipping rules belong to a shipping method. Each rule is checked one by one, in order, against the cart to see if it is a match. The first rule that matches the cart supplies the pricing to the shipping method. If no rules match the cart, then that shipping method is not available for the customer to select.

The matching of the shipping rules to the cart is based on the follow rules conditions:

### Shipping Zone

Each shipping rule has a single zone. The shipping address of the cart is determined to be within or outside the rule’s shipping zone.

### Shipping Categories

The shipping rule has options for each category in the system. Each shipping category can be set to:

1. Allow. Products of this shipping category can be allowed for this shipping method.
2. Disallow. If products of this shipping category are found in the cart the rule will not match the cart.
3. Require. Products of this shipping category must exist in the cart for this rule to match.

### Order Contents

In addition to the shipping zone and shipping category matching, various order information can be used to determine a match of the rule, such as order cost, order item quantity, and total order weight.

## Shipping Rule Conditions

### Shipping Zone

This condition is met if the order’s shipping address falls within this zone.

### Minimum Order Quantity

This condition is met if the order has at least a certain number of items.

### Maximum Order Quantity

This condition is met if the order quantity does not exceed a certain number of items.

### Minimum Order Total Price

This condition is met if the total order price is at least a certain amount.

### Maximum Order Total Price

This condition is met if the total order price is no more than a certain amount.

### Minimum Order Total Weight

This condition is met if the total order weight is at least a certain amount.

### Maximum Order Total Weight

This condition is met if the total order weight is no more than a certain amount.

### Shipping Categories

For each shipping categories, this rule can allow, disallow, or require certain products to match this rule.

## Shipping Rule Costs

### Base Rate

Set a base shipping rate for the order as a whole. This is a shipping cost added to the order as a whole and not to a single line item.

### Minimum Rate

The minimum the person should spend on shipping.

### Maximum Base Rate

The maximum the person should spend on shipping after adding up the base rate plus all item level rates.

### Item level rates:

### Default Per Item Rate

Set a per order item shipping rate.

### Default Item Weight Rate

Cost per whole unit of the store’s dimension units. For example, if you set your dimension unit option to Kilos, and your product weight was 1.4Kg, and you enter 1 as the item weight rate, then the price will be $1.4

### Default Percentage Item Price Rate

The amount based on a percentage of items cost.

In addition to the default item level rates, you can override the default values with shipping category specific per item, weight, and percentage rates.

::: warning
If a customer changes their shipping address during checkout, a previously selected shipping method may no longer match and will be immediately removed as the shipping method set on the cart.
:::
