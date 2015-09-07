# 0.7.95

## Breaking Changes

- [Purchasable Interface](#) now requires a `getSnapShot()` method.
-

## Fixed Bugs and Issues


## Improvements Made

- As much data about purchasable (variant) is now included in a `snapshot` attribute. Use it to access information about the purchasable. This is useful in the cases where variants were deleted after being ordered.
-

#  0.6.85

## Fixed Bugs and Issues

- SKU is marked as required in UI on variants
- The `customer` param on order element query now works correctly. Thus enabling `{% set orders = craft.market.orders.customer(craft.market.customer).find() %}`
- Fixed bug when editing price with commas. (For now price formatting is turned off until we get localization support for products)
- Fixed bug where a validation error on an existing product took user to the new product screen.

## Improvements Made
- Variant Index UI inside a product improved
- Product Field given a clearer name.
- Default credit card expiry year set to 2016 to ease testing
- More Ajaxified enpoints for cart controller actions
- Set the default orderPdfTemplate to the example template.
- Last used shipping and billing addresses now remembers and set as defaults for guest or logged in user.
- All guest orders with a matching email address to a user are consolidated to single user when that user logs in. This means that someone could checkout as a guest 5 times with the same email address, then eventually register and all 5 guest orders with the same email address will moved to that user.
- All Cart ajax endpoints now return a serializes json 'cart' object which includes line items and adjustments - all in json. This is only returns on `{'success':true}` ajax responses.

# 0.6.84

## Fixed Bugs and Issues

- Various Bug Fixes.
- Line Items can not have negative total
- Drag and drop ordering on shipping rates fixed.
- From name and email settings now used in emails.
- Fix included tax calc on non default zone.

## Improvements Made

- Standardized all front-end session notification names to 'notice' or 'error'. See example templates.
- Check purchsable id added to cart implements Purchasable Interface else reject.
- PDF Order generator url. Use `order.pdfUrl` in twig or `order->pdfUrl` in php. You can also pass a flag name to use as a switch in the twig pdf template `order.getPdfUrl('customer')`.
- Example Pdf template located in example templates in `commerce/_pdf/order.html` set this in your global commerce settings.
- Deletable Addresses in customer edit screen.
- Ensure customer can only edit or delete own address from frontend.
- Must have at least one tax category
- Require email address on order before checkout is able to be completed.
- Settings Menu layout improvements
- Better UX for discounts and sales UI.
- Variant edit UI improvements.
- Nicer formatting for numbers in CP.
- Master Variant now hidden and auto SKU generated.
- Simple Product edit screen improved a little.
- Improve example templates when product of type variants, has no variants.
- Save and continue editing available on products edit screen. Also CMD+S enabled.
- Variants deletable from listing.
- Discount cannot be greater than sale price.
- Always show sale price in example templates.
- New `notes` field updatable on lineitem.
- Ajaxable cart add and update
- Validate entered tax rate better
- Manual gateway does not require a credit card form.
- Reorganise Order Screen
- Variants now inherit the products url but appends ?variant={id} this means you can do `variant.url` in tempaltes.
- New default cart cookie expiring setting in global settings.
- Added a purge abandoned carts interval to global settings.
- Added edit variant action to listing, this enabled editing a variant inplace.

## Breaking Changes

- Removed the config settings from config.php and moved into settings UI. (Purge cart settings)

- Rename of all AddressService methods
- `save -> saveAddress`
- `deleteById -> deleteAddressById`
- `getById -> getAddressById`
- `getAllByCustomerId -> getAddressesByCustomerId`

# Renaming of core order and line item attrbutes. This will break your templates. Below is the old and new name names:
- `lineitem.taxAmount -> lineitem.tax`
- `lineitem.shippingAmount -> lineitem.shippingCost`
- `lineitem.discountAmount -> lineitem.discount`
- `lineitem.optionsJson -> lineitem.snapshot`
- `order.baseShippingRate -> lineitem.baseShippingCost`
- `order.finalPrice -> lineitem.totalPrice`
- `order.paidTotal -> lineitem.totalPaid`
- `order.paidAt -> lineitem.datePaid`
- `order.completedAt -> lineitem.dateOrdered`

# 0.6.81

## Fixed Bugs and Issues

- Various Bug Fixes.

## Improvements Made

- Can now edit existing variants in a HUD modal window by double-clicking next to the variant title.
- Order Statuses are now first class citizens in Settings.
- Order Listing sidebar improved with status icons.

## Breaking Changes

- `orderTypeHandle` hidden param is no longer required in any front end template. It's been removed from example templates.
- The important `craft.market.getCart('order')` method in your templates does now not expect any params. Call the method without a param like so: `craft.market.getCart()`

# 0.6.79

## Fixed Bugs and Issues

- Many Bug Fixes.
- Users editing their addresses do not affect past order's addresses.
- Guest users now have their email added to the order.
- Payment form now checks validity of credit card on validation and strips non integer chars.
- Gateway Credit Card object email set from set from `order.email`, not user.
- jQuery now included from a CDN in example templates. Updated to 2.1.4.

## Improvements Made

- New, more clear, example templates design with new folder named 'commerce'. No longer named 'market'.
- Qty defaults to 1 when add to cart controller action is run with no qty param.
- Deprecated the `Market_OrderModel::showAddress()` and `Market_OrderModel::showPayment()` view logic from the order/cart model. You should use your own twig logic instead.
- Simplified example template errors. All errors are not set to the standard Craft Flash session 'error' and 'info' variables.  See [Flash Messages](http://craftcms.stackexchange.com/questions/4586/how-to-display-a-flash-message-in-a-template).
- Default Product Types created during install have urls set to example templates.
- Default Shipping Rule created on install as a catch all rule.
- The `redirect` and `cancelUrl` params are run through the url template parser so your redirect actions can be used like this: `<input type="hidden" name="redirect" value="order/{number}"/>`. This would redirect to something like `commerce/54he7318fcn93n94r`
- Customer's order listing example templates added.
- Orders now have `paidAt` and `paidTotal` attributes. You can also use the `isPaid()` method on the order to determine if the order is paid.
- Free (Zero value) carts will complete immediately without processing the credit card form. This will be turned into an optional setting in the future.
- Order CP edit screen UI improved slightly. More UI improvements coming soon.


## Features Added

- Single Products (that do not have variants) meet purchasable interface, and addable to cart with `product.purchasableId` which returns the master variant's id.
- Simple seed data now created on install. No need to be in devMode to isntall seed data
- Addresses are now cached to the Order when order is completed. All references to addresses on order after completion use these cached addresses.
- Example guest checkout form added to example templates.
- Order Status colors are now a fixed set of predefined colors. The color picker has been removed.

## Breaking Changes

- Removed ability to add additional Order Types. Future update will remove the concept of Order Types. Prepare for this by using a single Order Type named 'order'.
- Default Shipping Method moved off from Order Type to Shipping Methods settings.
- Cart payment form now requires the `redirect` and `cancelUrl` params. `returnUrl` is no longer used and will result in an error.


# 0.6.75

## Fixed Bugs and Issues

- Bug fix cart number generated incorrect string length
- Updated example templates to remove multiple order types examples.

# 0.6.75

## Fixed Bugs and Issues

- Migration error fixed where foreign keys did not exist.

# 0.6.74

## Fixed Bugs and Issues

- Bug fix. Unused field on Order Type model removed.

# 0.6.73

## Fixed Bugs and Issues

- Variety of general bug fixes.
- Shipping calculation errors when no Addresses set.
- Fixed a bug where master variant would not show vaildation errors when product saved correctly.

## Improvements Made

- Variants now do not inherit values automatically. In the future price, weight, etc will be inheritable and overridable in Product Type config.
- Variant listing UI in Product edit screen.
- Moved `purgeIncompleteCartDuration` to market.php config file and out of Order Type. Purge will attempt on ever order listing screen based on date. Default set to 3 months.

## Features Added

- Added maxQty option to products and logic to cart.
- Variants are now Purchasable Elements and have custom field layouts. Adding to cart now requires a `purchasableId`.

# 0.6.72

## Fixed Bugs and Issues

- Fixed error when no country was set on the cart while looking up shipping costs.


# 0.6.70

## Fixed Bugs and Issues

- Fixed CP nav alignment
- Updated Jquery version which fixes broken example templates in Craft 2.4 (Will use a cdn in next release)
- Fixed inclusive tax calculation error
- Included missing Product fields on element model data returned to templates.

## Improvements Made

- Switched to new version numbering system to be compatible with P&T build process.

## Features Added
- Product Urls now work and resolve to a global `product` variable when Product's route is hit. You can have unique urls per Product Type. This works the same was as the `entry` variable.
- Shipping rules are now nested under shipping Methods in the UI and are now orderable. First rule in order to match gets used.



# 0.5.49.280

## Fixed Bugs and Issues

- Selectize.js overflow hidden bug fixed.
- Ensure order status can only be set to null while order is incomplete.
- Variant stock count defaults to zero now when unlimited stick is unchecked.
- Order Status change error fixed when no emails are present.
- Tax Calculation bug that would cause duplicate tax to occur with multiple variants in cart.
- Missing orders in CP Order listing while cart was incomplete.

## Improvements Made

- Breadcrumbs in CP are improved.
- City is now a field on address models.
- Completed Date added to order listing.
- Order Listing sidebar now focuses on statuses.
- Changed User to Customer on Order Status history.

## Features Added

- Discount Coupon “per user” and “total uses” now available.
- Purge incomplete carts by order type available.
- New and improved CP navigation.
- Customer Listing and Address Edit in CP available.
- Product Selection Fieldtype added.
- Customer updatable fields on order/cart update using `<input name="fields[customFieldName]" ...`
