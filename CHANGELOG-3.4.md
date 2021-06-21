# Release Notes for Craft Commerce 3.4

### Added
- Added the ability to download multiple orders’ PDFs as a single, combined PDF from the Orders index page. ([#1785](https://github.com/craftcms/commerce/issues/1785))
- Added the ability to disable included tax removal. ([#1881](https://github.com/craftcms/commerce/issues/18813))
- Added the “Revenue Options” setting to the Top Products widget. ([#1919](https://github.com/craftcms/commerce/issues/1919))
- Added the ability to bulk-delete discounts from the Discounts index page. ([#2172](https://github.com/craftcms/commerce/issues/2172))
- Added the ability to bulk-delete sales from the Sales index page.
- Added the `cp.commerce.discounts.index`, `cp.commerce.discounts.edit`, `cp.commerce.discounts.edit.content`, and `cp.commerce.discounts.edit.details` template hooks. ([#2173](https://github.com/craftcms/commerce/issues/2173))
- Added the `cp.commerce.sales.index`, `cp.commerce.sales.edit`, `cp.commerce.sales.edit.content`, and `cp.commerce.sales.edit.details` template hooks. ([#2173](https://github.com/craftcms/commerce/issues/2173))
- Added `craft\commerce\base\Plan::$dateCreated`.
- Added `craft\commerce\base\Plan::$dateUpdated`.
- Added `craft\commerce\models\Address::$dateCreated`.
- Added `craft\commerce\models\Address::$dateUpdated`.
- Added `craft\commerce\models\Country::$dateCreated`.
- Added `craft\commerce\models\Country::$dateUpdated`.
- Added `craft\commerce\models\Customer::$dateCreated`.
- Added `craft\commerce\models\Customer::$dateUpdated`.
- Added `craft\commerce\models\PaymentCurrency::$dateCreated`.
- Added `craft\commerce\models\PaymentCurrency::$dateUpdated`.
- Added `craft\commerce\models\Sale::$dateCreated`.
- Added `craft\commerce\models\Sale::$dateUpdated`.
- Added `craft\commerce\models\ShippingAddressZone::$dateCreated`.
- Added `craft\commerce\models\ShippingAddressZone::$dateUpdated`.
- Added `craft\commerce\models\ShippingCategory::$dateCreated`.
- Added `craft\commerce\models\ShippingCategory::$dateUpdated`.
- Added `craft\commerce\models\ShippingMethod::$dateCreated`.
- Added `craft\commerce\models\ShippingMethod::$dateUpdated`.
- Added `craft\commerce\models\ShippingRule::$dateCreated`.
- Added `craft\commerce\models\ShippingRule::$dateUpdated`.
- Added `craft\commerce\models\State::$dateCreated`.
- Added `craft\commerce\models\State::$dateUpdated`.
- Added `craft\commerce\models\TaxAddressZone::$dateCreated`.
- Added `craft\commerce\models\TaxAddressZone::$dateUpdated`.
- Added `craft\commerce\models\TaxCategory::$dateCreated`.
- Added `craft\commerce\models\TaxCategory::$dateUpdated`.
- Added `craft\commerce\models\TaxRate::$dateCreated`.
- Added `craft\commerce\models\TaxRate::$dateUpdated`.
- Added `craft\commerce\models\TaxRate::$removeIncluded`.
- Added `craft\commerce\models\TaxRate::$removeVatIncluded`.
- Added `craft\commerce\stats\TopProducts::$revenueOptions`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_DISCOUNT`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_SHIPPING`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_TAX_INCLUDED`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_TAX`.
- Added `craft\commerce\stats\TopProducts::TYPE_QTY`.
- Added `craft\commerce\stats\TopProducts::TYPE_REVENUE`.
- Added `craft\commerce\stats\TopProducts::createAdjustmentsSubQuery`.
- Added `craft\commerce\stats\TopProducts::getAdjustmentsSelect`.
- Added `craft\commerce\stats\TopProducts::getGroupBy`.
- Added `craft\commerce\stats\TopProducts::getOrderBy`.
- Added libmergepdf.

### Changed
- Craft Commerce now requires Craft CMS 3.7 or later.
- Product slideouts now include the full field layout and meta fields. ([#2205](https://github.com/craftcms/commerce/pull/2205))
- Discounts now have additional user group condition options. ([#220](https://github.com/craftcms/commerce/issues/220))
- The order field layout no longer validates if it contains a field called `billingAddress`, `customer`, `estimatedBillingAddress`, `estimatedShippingAddress`, `paymentAmount`, `paymentCurrency`, `paymentSource`, `recalculationMode` or `shippingAddress`.
- Product field layouts no longer validate if they contain a field called `cheapestVariant`, `defaultVariant` or `variants`.
- Variant field layouts no longer validate if they contain a field called `description`, `price`, `product` or `sku`.
- Order notices are now cleared when orders are completed. ([#2116](https://github.com/craftcms/commerce/issues/2116))
- Donations, orders, products, and variants now support `EVENT_DEFINE_IS_EDITABLE` and `EVENT_DEFINE_IS_DELETABLE`. ([craftcms/cms#8023](https://github.com/craftcms/cms/issues/8023))
- Address, customer, country, state, payment currency, promotion, shipping, subscription plan, and tax edit pages now display date meta info.

### Fixed
- Fixed a bug where discounts weren’t displaying validation errors for the “Per Email Address Discount Limit” field. ([#1455](https://github.com/craftcms/commerce/issues/1455))
- Fixed a UI bug with Order Edit page template hooks. ([#2148](https://github.com/craftcms/commerce/issues/2148))
