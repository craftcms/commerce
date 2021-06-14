# Release Notes for Craft Commerce 3.4

## 3.4 - Unreleased

### Added
- Added the ability to download collated PDFs when downloading PDFs for more than one Order from the Edit Order page. ([#1785](https://github.com/craftcms/commerce/issues/1785))
- Added additional user group conditon options on the Edit Discount page. ([#220](https://github.com/craftcms/commerce/issues/220))
- Added the ability to custom the revenue calculations in the Top Product stat. ([#1919](https://github.com/craftcms/commerce/issues/1919))
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

### Changed
- Craft Commerce now requires Craft CMS 3.7.0 or later.
- Order notices are now cleared form the cart when the order is completed. ([#2116](https://github.com/craftcms/commerce/issues/2116))
- Donations, orders, products, and variants now support `EVENT_DEFINE_IS_EDITABLE` and `EVENT_DEFINE_IS_DELETABLE`. ([craftcms/cms#8023](https://github.com/craftcms/cms/issues/8023))
- Address edit page now displays date meta information.
- Customer edit page now displays date meta information.
- Country and State edit pages now displays date meta information.
- Payment Currency edit page now displays date meta information.
- Promotion edit pages now displays date meta information.
- Shipping edit pages now displays date meta information.
- Subscription Plan edit page now displays date meta information.
- Tax edit pages now displays date meta information.

### Fixed
- Fixed a bug where the incorrect error explanation was returned for the discount `perEmailLimit` validation. ([#1455](https://github.com/craftcms/commerce/issues/1455))
