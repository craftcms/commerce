# 0.7.95

## Breaking Changes

- removed `lineItem.underSale`, use `lineItem.onSale`. (Duplicate Methods)
- Renamed variant attribute `isMaster` to `isImplicit`.
- `product.variants` now returns normal variants if the product type has variants, or an array containing just the implicit variant if the product type has no variants.
- [Purchasable Interface](#) now requires a `getSnapShot()` method that returns an array.
- Variants are now deleted when the product is deleted. No more deletedAt date on variants.
- Address management in templates is now all handled in the context of the order. See example templates.
- Purchasable table now tracks all Purchasable's id, price and sku. If you have a custom Purchasable you need to make a migration that adds your Purchasables to this table and use `craft()->market_purchasable->saveElement()` instead of `craft()->elements->saveElement()`. See the [Purchasable](http://buildwithmarket.com/docs/developers/purchasables) docs.
- Ajax response on cart actions contains additional data. Adjustments are now grouped by their type.
- Removed inflection twig filters. Please use a 3rd party inflection plugin. Only core filters are `|marketDecimal` and `|marketCurrency`.
- **Products no longer charade themselves as purchasables**. You must now explicitly add the products implicid variant's id to the cart as the purchasabelId. Look at the example templates for more information.
- Order Status change event now returns `orderHistory` and not `orderHistoryModel` see the [events reference](http://buildwithmarket.com/docs/developers/events-reference)

## Fixed Bugs and Issues

- Fixed decimals without commas, which are now used in price fields
- Fixed issue where some variants were being orphaned
- Remove any orphaned order, product, or variant elements in the elements table but not in the core commerce tables.
- Fixed order example templates and CP order page to only reference the snapshot data or lineItem data. Avoid referencing the original purchasable (variant) after order is complete.
- LineItem is removed from cart if Qty is updated to be zero.
- Fixed bug for maxQty when adding to the cart.
- All CP controller actions are now Admin only, until we get more advanced permissions system built out.
- Snapshots are now contain more information about the Purchasable on the lineItem.
- salePrice now saved on the lineItem for core Purchasables.
- Fixed issue where sku was not being saved on an implicid variant.
- Fixed product `availableOn` and `expiresOn` query params.
- Fixed issue where order was not updated in the ajax response after modifying lineItems.

## Improvements Made

- As much data about purchasable (variant) is now included in a `snapshot` attribute on the lineItem. Use it to access information about the purchasable. This is useful in the cases where variants are deleted after being ordered.
- Improved Variant listing in product screen.
- Better instructions on Variant edit screen.
- Remove unused core settings currencySymbol,currencySuffix,currencyDecimalPlaces,currencyDecimalSeperator
- Show the core dimensions settings on variant entry dimension fields.
- **Mass Edit** Variants action. You can now bulk update core variant values like price and weight in a single action.
- If no fromEmail and fromName are set in core commerce settings, use the Craft system email settings defaults.
- **Promotable Flag** on product edit screen. Control whether a product should be subject to any discount or sales rules.
- **Free Shipping Flag** on product edit screen. Control whether a product should have free shipping irregardless of shipping rules.
- Better logging for customer consolidation processing.
- Better Order ajax response.
- `|marketDecimal` now has an optional param for showing commas. Use: `9000.00|marketDecimal` will now result in `9000.00` and `|marketDecimal(true)` will now result in  `9,000.00`
- `|marketCurrency` now has optional param for not stripping zero cents. `9000.00|marketCurrency(true)` will now result in `9000.00`
- Cleaned up base plugin folder using `/etc` dir.
- **Variant Titles** have been added. Use the template string on the product type to build a title from the custom fields.
- Title field on variants is now used for lineItem description.
- `totalLineItems` and `totalAdjustments` values added to cart ajax responses.
- **Adjusters** can now be supplied to the system. See the [Adjusters](http://buildwithmarket.com/docs/developers/adjusters) docs.
- A note post param can be included in the addToCart form action.


#  0.6.85

## Fixed Bugs and Issues

- Various Bug Fixes.
- SKU is marked as required in UI on variants
- The `customer` param on order element query now works correctly. Thus enabling `{% set orders = craft.market.orders.customer(craft.market.customer).find() %}`
- Fixed bug when editing price with commas. (For now price formatting is turned off until we get localization support for products)
- Fixed bug where a validation error on an existing product took user to the new product screen.

## Improvements Made
- Variant Index UI inside a product improved
- Product Field given a clearer name.
- Default credit card expiry year set to 2016 to ease testing
- More Ajaxified endpoints for cart controller actions
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
