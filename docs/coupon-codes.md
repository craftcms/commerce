# Coupon Codes

Coupon codes are set up as a condition within a discount promotion. 

To create a new discount, go to Commerce → Promotions → Discounts in the Control Panel. 
To see the coupon condition, go to the “Coupon” tab.

Discounts are only available in the Pro edition of Craft Commerce.

An empty coupon field on the discount means there is no requirement for a coupon
for the discount to work. Adding a coupon requires that a coupon is submitted to 
the cart. This makes the discount available to match the order but still needs to match all other discount conditions.

Read more about [Discounts](discounts.md).

## Using a coupon

To add a coupon to the cart, a customer submits the `couponCode` parameter to the cart using the `commerce/cart/update-cart` form action.

Example:

```twig
<form method="POST">
<input type="hidden"
       name="action"
       value="commerce/cart/update-cart">
{{ redirectInput('shop/cart') }}
{{ csrfInput() }}

<input type="text"
       name="couponCode"
       class="{% if cart.getFirstError('couponCode') %}has-error{% endif %}"
       value="{{ cart.couponCode }}"
       placeholder="{{ "Coupon Code"|t }}">
       
<input type="submit" value="Update Cart"/>
<form>
```

Only one coupon code can exist on the cart at a time. The current coupon code 
submitted to the cart can be seen by outputting `{{ cart.couponCode }}`.

You can retrieve the discount associated with the coupon code with:

```twig
{% set discount = craft.commerce.discounts.getDiscountByCode(cart.couponCode) %}
{% if discount %}
{{ discount.name }} - {{ discount.description }}
{% endif %}
```




 

 