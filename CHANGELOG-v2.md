
### Added
- Added 'Copy' Reference tag to Product actions.

### Changed
- `paymentMethodSettings` setting is now called `gatewaySettings` and it now uses handles to reference gateways instead of IDs.
- `Payment Methods` are now called `Gateways` and this is reflected across the entire plugin and it's API.


### Event changes
- `craft\commerce\elements\Orders` now fires the following events: `beforeCompleteOrder`, and `afterCompleteOrder`.
- `craft\commerce\services\Addresses` now fires the following events: `beforeSaveAddress`, and `afterSaveAddress`.
- `craft\commerce\services\Cart` now fires the following events: `beforeAddToCart`, `afterAddToCart`, `afterRemoveFromCart` and a cancelable `beforeRemoveFromCart` event.
- `craft\commerce\services\Discounts` now fires the cancelable `beforeMatchLineItem` event.
- `craft\commerce\services\Emails` now fires the following events: `afterSendEmail`, and a cancelable `beforeSendEmail`.
- `craft\commerce\services\LineLitems` now fires the following events: `beforeSaveLineItem`, `afterSaveLineItem`, `createLineItem`, and `populateLineItem`.
- `craft\commerce\services\OrderHistories` now fires the `orderStatusChange` event.
- `craft\commerce\services\Payments` now fires the following events: `beforeCaptureTransaction`, `afterCaptureTransaction`, `beforeRefundTransaction`, `afterRefundTransaction` and a cancelable `beforeGatewayRequestSend` event.
- `craft\commerce\services\Transactions` now fires the `afterSaveTransaction` event.
- `craft\commerce\services\Variants` now fires the `purchaseVariant` event.

### Events that used to be hooks
- Instead of the `commerce_modifyPaymentRequest` hook you should use the `beforeGatewayRequestSend` event fired by `craft\commerce\base\Gateway`.
- Instead of the `commerce_modifyGatewayRequestData` hook you should use the `beforeSendPaymentRequest` event fired by `craft\commerce\services\Payments`.
- Instead of the `commerce_modifyItemBag` hook you should use the `afterCreateItemBag` event fired by `craft\commerce\base\Gateway`.
- Instead of the `commerce_modifyEmail` hook you should use the cancelable `beforeSendEmail` event fired by `craft\commerce\services\Emails`.
- Instead of the `commerce_registerOrderAdjusters` hook you should use the `registerOrderAdjusters` event fired by `craft\commerce\services\OrderAdjustments`.
- To register new gateway types, use the `registerGatewayTypes` event fired by `craft\commerce\services\Gateways`.
- The `commerce_modifyOrderSources`, `commerce_getOrderTableAttributeHtml`, `commerce_getProductTableAttributeHtml`, `commerce_defineAdditionalOrderTableAttributes`, `commerce_defineAdditionalProductTableAttributes` hooks have been replaced by more generic Craft 3 hooks.

### Removed
- Removed `craft\commerce\services\Countries::getCountryByAttributes()`
- Removed `craft\commerce\services\States::getStatesByAttributes()`