- Added the `registerRedactorPlugin` event to `craft\fields\RichText`,

### Changed
- `craft\commerce\services\Addresses` now fires the following events: `beforeSaveAddress`, and `afterSaveAddress`.
- `craft\commerce\services\Cart` now fires the following events: `beforeAddToCart`, `afterAddToCart`, `afterRemoveFromCart` and a cancelable `beforeRemoveFromCart` event.
- `craft\commerce\services\Discounts` now fires the cancelable `beforeMatchLineItem` event.
- `craft\commerce\services\Emails` now fires the following events: `afterSendEmail`, and a cancelable `beforeSendEmail`.
- `craft\commerce\services\LineLitems` now fires the following events: `beforeSaveLineItem`, `afterSaveLineItem`, `createLineItem`, and `populateLineItem`.
- `craft\commerce\services\Orders` now fires the following events: `beforeSaveOrder`, `afterSaveOrder`, `beforeCompleteOrder`, and `afterCompleteOrder`.
- `craft\commerce\services\OrderHistories` now fires the `orderStatusChange` event.
- `craft\commerce\services\Payments` now fires the following events: `beforeCaptureTransaction`, `afterCaptureTransaction`, `beforeRefundTransaction`, afterRefundTransaction` and a cancelable `beforeGatewayRequestSend` event.
- `craft\commerce\services\Products` now fires the following events: `beforeSaveProduct`, `afterSaveProduct`, `afterDeleteProduct` and a cancelable `beforeDeleteProduct` event.
- `craft\commerce\services\Transactions` now fires the `afterSaveTransaction` event.
- `craft\commerce\services\Variants` now fires the `purchaseVariant` event.
