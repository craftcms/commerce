# Discounts

Discounts are deductions off line items and an order, while products are in the cart. Discounts are only calculated while items are in the cart, [Sales](sales.md) can apply to a product outside of the cart context.

To create a new discount, go to Commerce → Promotions → Discounts in the Control Panel.

## Ordering

Discounts are processed and applied in the order they are sorted in the Control Panel.

By setting the *Stop processing further discounts after this discount matches* checkbox on a discount, and if that discount conditions matches, no further discounts will be applied to the cart.

## Coupon Discounts

Discounts can optionally be a coupon based discounts. Coupon discounts have a coupon code entered in as a special condition on the “Coupon” tab.

If no coupon is entered, this discount is matched on all other conditions configured. If a coupon is added to the discount, all other conditions still need to be met in addition to the coupon being applied to the cart.

If you enter a coupon code as discount condition, additional conditions are shown that pertain to a coupon discount:

### Per User Coupon Limit

How many times one user is allowed to use this discount. Setting this requires a user to be logged in to use the discount. Setting this limit will not allow guests to use the discount. Set to zero for unlimited use of this coupon by guests or users.

This limit is controlled at the discount ID level, meaning if you change the coupon code itself, the counter still applies.

### Per Email Address Coupon Limit

How many times one email address is allowed to use this discount. This applies to all previous orders, whether guest or user. Set to zero for unlimited use by guests or users.

This limit is controlled at the discount coupon code itself, so changing the code on a discount will reset this limit condition.

### Total Coupon Use Limit

How many times this coupon can be used in total by guests or logged in users. Set zero for unlimited use.

This limit is controlled at the discount ID level, meaning if you change the coupon code itself, the counter still applies.

### Times Coupon Used

Read only field that counts how many times this coupon has been used, if a total coupon usage limit has been set.

This limit is controlled at the discount ID level, meaning if you change the coupon code itself, the counter still applies.

### Reset Counter

After this coupon has been used at least once, there is the ability to reset all usage counters. This applies to all conditions based on the discount ID, not the “Per Email Address Coupon Limit”, which is based on the coupon code itself.
