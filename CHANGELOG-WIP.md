# Release Notes for Craft Commerce 5.1 (WIP)

## 5.1.0

### Store Management
- It’s now possible to create custom line items.
- Added the `commerceCustomLineItem()` Twig function.

### Administration
- Added a new “Manage subscription plans” permission.
- Added a new “Manage donation settings” permission.
- Added a new “Manage store general setting” permission.
- Added a new “Manage payment currencies” permission.

### Development

### Extensibility
- Added `craft\commerce\enums\LineItemType`.
- Added `craft\commerce\helpers\LineItem::generateCustomLineItemHash()`.
- Added `craft\commerce\models\LineItem::$type`.
- Added `craft\commerce\models\LineItem::populate()`.
- Added `craft\commerce\models\LineItem::refresh()`.
- Added `craft\commerce\models\LineItem::getHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::setHasFreeShipping()`.
- Added `craft\commerce\models\LineItem::getIsPromotable()`.
- Added `craft\commerce\models\LineItem::setIsPromotable()`.
- Added `craft\commerce\models\LineItem::getIsShippable()`.
- Added `craft\commerce\models\LineItem::setIsShippable()`.
- Added `craft\commerce\models\LineItem::getIsTaxable()`.
- Added `craft\commerce\models\LineItem::setIsTaxable()`.
- Added `craft\commerce\models\Order::EVENT_AFTER_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\models\Order::EVENT_BEFORE_LINE_ITEMS_REFRESHED`.
- Added `craft\commerce\services\LineItems::create()`.
- Added `craft\commerce\services\LineItems::resolveCustomLineItem()`.
- Deprecated `craft\commerce\models\LineItem::populateFromPurchasable()`. Use `populate()` instead.
- Deprecated `craft\commerce\models\LineItem::refreshFromPurchasable()`. Use `refresh()` instead.
- Deprecated `craft\commerce\services\LineItems::createLineItem()`. Use `create()` instead.

### System
- Craft Commerce now requires Craft CMS 5.2 or later.
