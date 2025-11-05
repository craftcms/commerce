# Release Notes for Craft Commerce 5.5 (WIP)

### Store Management
- It is now possible to suppress order emails when marking an order as complete in the control panel. ([#4144](https://github.com/craftcms/commerce/issues/4144))
- PDF download links now include time-limited security tokens that expire after a configurable duration (default 24 hours).
- Anonymous users attempting to download a PDF with an expired or missing token are now shown an email verification form with a masked email address (e.g., `j***@example.com`).
- PDF link expiry duration can now be configured per PDF in Settings → PDFs.
- Logged-in users who own the order or have appropriate permissions bypass the email verification flow and can download PDFs directly.
- Added a new system email message for sending PDF download links to customers.

### Administration
- Added billing and shipping address conditions to gateways. ([#4100](https://github.com/craftcms/commerce/pull/4100))
- Added preview targets for products. ([#4128](https://github.com/craftcms/commerce/pull/4128))
- Added slug translation options to product types. ([#4088](https://github.com/craftcms/commerce/pull/4088))
- Gateway condition rules now allow multiple gateways to be selected. ([#4112](https://github.com/craftcms/commerce/issues/4112))
- Product action menus now have “Product type settings” action, for admin users on environments that allow admin changes. ([#4157](https://github.com/craftcms/commerce/issues/4157))
- Added "Link Expiry" field to PDF settings, allowing administrators to configure how long PDF download links remain valid.

### Development
- Orders now have a `dateFirstPaid` property that records the date and time when the order was first paid in full.
- Improved product and variant query performance.
- Improved the performance of retrieving a line item’s catalog pricing rule ID.
- Added the `children`, `parent`, `ancestors` and `descendants` fields to products’ GraphQL data. ([#4122](https://github.com/craftcms/commerce/issues/4122))
- Added the `--force` option to the `commerce/reset-data` command. ([#4115](https://github.com/craftcms/commerce/discussions/4115))

### Extensibility
- Added `craft\commerce\controllers\DownloadsController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfChallenge()`.
- Added `craft\commerce\controllers\DownloadsController::actionPdfSent()`.
- Added `craft\commerce\elements\Order::$dateFirstPaid`.
- Added `craft\commerce\elements\Order::getMaskedEmail()`.
- Added `craft\commerce\elements\db\OrderQuery::$dateFirstPaid`.
- Added `craft\commerce\elements\db\OrderQuery::dateFirstPaid()`.
- Added `craft\commerce\events\InventoryMovementEvent`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `craft\commerce\events\UpdateInventoryLevelEvent`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `craft\commerce\helpers\Gql::getSchemaContainedProductTypes()`.
- Added `craft\commerce\models\Email::$renderSiteId`.
- Added `craft\commerce\models\Email::getRenderSite()`.
- Added `craft\commerce\models\Pdf::$linkExpiry`.
- Added `craft\commerce\records\Email::$renderSiteId`.
- Added `craft\commerce\records\Order::$dateFirstPaid`.
- Added `craft\commerce\services\CatalogPricingRules::hasCatalogPricingRules()`.
- Added `craft\commerce\services\Discounts::appendCouponCode()`. ([#4084](https://github.com/craftcms/commerce/pull/4084))
- Added `craft\commerce\services\Inventory::EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `craft\commerce\services\Inventory::EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `craft\commerce\services\Pdfs::getPdfUrl()` now generates secure tokenized URLs with expiry timestamps.

### System
- Fixed a bug where purchasables could have a shipping category that was no longer available to their product type. ([#4018](https://github.com/craftcms/commerce/issues/4018))
- Fixed a bug where order emails weren’t always getting rendered for the correct site.