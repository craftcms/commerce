# Discounts

Discounts are deductions off line items or deduction off the order as a whole. 
Discounts are only calculated *while* items are in the cart, [Sales](sales.md) are pricing 
rules that apply to product *before* they are added to the cart.

Discounts are only available in the Pro edition of Craft Commerce.

To create a new discount, go to Commerce → Promotions → Discounts in the Control Panel.

## Discount Sort Order

Discounts are processed and applied in the order they are sorted in the Control Panel.

Inside a discount there is a checkbox labelled “Stop processing further discounts after this discount matches”. 
If this checkbox is turned on, and the discount matches the order, no further discounts will be applied to the cart.

## Coupon Discounts

Discounts can have a coupon requirement as an optional condition. The coupon condition can be 
found on the “Coupon” tab.

If no coupon is entered, and the discount has a coupon code, then the discount will not apply. 
If a coupon is added to the discount, all other conditions still need 
to be met in addition to the coupon being applied to the cart.

To update the coupon code on the cart see [coupon codes](coupon-codes.md) in the template guides.

If you enter a coupon code as discount condition, additional conditions are shown 
that pertain to a coupon based discount:

### Per User Coupon Limit

How many times one user is allowed to use this discount. Setting this requires a 
user to be logged in to use the discount. Setting this limit will not allow guests to use the discount. Set to zero for unlimited use of this coupon by guests or users.

This limit is controlled at the discount ID level, meaning if you change the 
coupon code itself, the counter still applies.

### Per Email Address Coupon Limit

How many times one email address is allowed to use this discount. This applies 
to all previous orders, whether guest or user. Set to zero for unlimited use by guests or users.

This limit is controlled at the discount coupon code itself, so changing the code 
on a discount will reset this limit condition.

### Total Coupon Use Limit

How many times this coupon can be used in total by guests or logged in users. Set 
zero for unlimited use.

This limit is controlled at the discount ID level, meaning if you change the 
coupon code itself, the counter still applies.

### Times Coupon Used

Read only field that counts how many times this coupon has been used, if a total 
coupon usage limit has been set.

This limit is controlled at the discount ID level, meaning if you change the 
coupon code itself, the counter still applies.

### Reset Counter

After this coupon has been used at least once, there is the ability to reset all 
usage counters. This applies to all conditions based on the discount ID, not the 
“Per Email Address Coupon Limit”, which is based on the coupon code itself.
