# Editions

Craft Commerce comes in two editions, Lite and Pro. Both editions can be trialled for free in a local development environment, for as long as you need.

## Pro

The Pro edition of Craft Commerce is designed for professional ecommerce sites. This would usually include a cart, which customers would update, and a multi-page checkout flow.

The Pro edition has the following features that Lite does not:

- Discount promotions, which include coupon codes and custom rules that can reduce the price of items in the cart based on things like minimum order quantity or minimum order price. 
- Unlimited shipping methods that can have complex shipping rules.
- Custom tax rules based on configurable multi-state or multi-country zones.

## Lite

The Lite edition of Craft Commerce is designed for websites that just need the basics. For things like one-off product sales or subscriptions, where complex tax and shipping rules, promotion management, and shopping carts would be overkill.

The Lite edition has the following limitations that the Pro edition does not:

- Only one purchasable can be ordered in a single payment transaction.
- Shipping costs can be configured in settings as fixed amounts per order, with no shipping method choices for the customer.
- Tax costs can be configured as a fixed percentage of the order total, with no support for tax zones, or VAT ID validation.
- Discount promotions, including coupon codes, are not available.
- Custom adjusters can not be used to add additional costs and discounts.

In the Lite edition of Craft Commerce only a single line item can exist in the cart. Whenever a customer adds something to the cart, it replaces whatever item was in the cart. If multiple items are added to the cart
in a single request, only the last item that was submitted is added to the cart.

Although a cart always exists technically, a customer’s experience with Lite would probably not expose a cart UI on the front-end. For example, the customer probably shouldn't see a cart icon in the corner
of the page like you would see in a traditional ecommerce design. A single ‘buy now’ button should be the call-to-action and the customer would go straight to providing email, shipping and payment information on a single screen.
