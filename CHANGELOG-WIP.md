# Release Notes for Craft Commerce 6.0 (WIP)

### Catalog

- Added `CraftCms\Commerce\Catalog\Elements\Product`.
- Added `CraftCms\Commerce\Catalog\Elements\Variant`.
- Added `CraftCms\Commerce\Catalog\Queries\ProductQuery`.
- Added `CraftCms\Commerce\Catalog\Queries\VariantQuery`.
- Added `CraftCms\Commerce\Catalog\Models\Product`.
- Added `CraftCms\Commerce\Catalog\Models\Variant`.
- Added `CraftCms\Commerce\Catalog\Models\ProductTypeSite`.
- Added `CraftCms\Commerce\Catalog\Products`.
- Added `CraftCms\Commerce\Catalog\Variants`.
- Added `CraftCms\Commerce\Catalog\ProductType\Data\ProductType`.
- Added `CraftCms\Commerce\Catalog\ProductType\Models\ProductType`.
- Added `CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite`.
- Added `CraftCms\Commerce\Catalog\ProductType\ProductTypes`.
- Added `CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotDataEvent`.
- Added `CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotFieldsEvent`.
- Added `CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotDataEvent`.
- Added `CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotFieldsEvent`.
- Added `CraftCms\Commerce\Catalog\Events\ModifyPurchasablesTableQueryEvent`.
- Added `CraftCms\Commerce\Catalog\Events\ProductEvent`.
- Added `CraftCms\Commerce\Catalog\Events\ProductTypeEvent`.
- Added `CraftCms\Commerce\Catalog\Events\PurchaseVariantEvent`.
- Deprecated `craft\commerce\elements\Product`. `CraftCms\Commerce\Catalog\Elements\Product` should be used instead.
- Deprecated `craft\commerce\elements\Variant`. `CraftCms\Commerce\Catalog\Elements\Variant` should be used instead.
- Deprecated `craft\commerce\elements\db\ProductQuery`. `CraftCms\Commerce\Catalog\Queries\ProductQuery` should be used instead.
- Deprecated `craft\commerce\elements\db\VariantQuery`. `CraftCms\Commerce\Catalog\Queries\VariantQuery` should be used instead.
- Deprecated `craft\commerce\records\Product`. `CraftCms\Commerce\Catalog\Models\Product` should be used instead.
- Deprecated `craft\commerce\records\Variant`. `CraftCms\Commerce\Catalog\Models\Variant` should be used instead.
- Deprecated `craft\commerce\models\ProductType`. `CraftCms\Commerce\Catalog\ProductType\Data\ProductType` should be used instead.
- Deprecated `craft\commerce\models\ProductTypeSite`. `CraftCms\Commerce\Catalog\Models\ProductTypeSite` should be used instead.
- Deprecated `craft\commerce\records\ProductType`. `CraftCms\Commerce\Catalog\ProductType\Models\ProductType` should be used instead.
- Deprecated `craft\commerce\records\ProductTypeSite`. `CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite` should be used instead.
- Deprecated `craft\commerce\services\Products`. `CraftCms\Commerce\Catalog\Products` should be used instead.
- Deprecated `craft\commerce\services\Variants`. `CraftCms\Commerce\Catalog\Variants` should be used instead.
- Deprecated `craft\commerce\services\ProductTypes`. `CraftCms\Commerce\Catalog\ProductType\ProductTypes` should be used instead.
- Deprecated `craft\commerce\events\CustomizeProductSnapshotDataEvent`. `CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotDataEvent` should be used instead.
- Deprecated `craft\commerce\events\CustomizeProductSnapshotFieldsEvent`. `CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotFieldsEvent` should be used instead.
- Deprecated `craft\commerce\events\CustomizeVariantSnapshotDataEvent`. `CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotDataEvent` should be used instead.
- Deprecated `craft\commerce\events\CustomizeVariantSnapshotFieldsEvent`. `CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotFieldsEvent` should be used instead.
- Deprecated `craft\commerce\events\ModifyPurchasablesTableQueryEvent`. `CraftCms\Commerce\Catalog\Events\ModifyPurchasablesTableQueryEvent` should be used instead.
- Deprecated `craft\commerce\events\ProductEvent`. `CraftCms\Commerce\Catalog\Events\ProductEvent` should be used instead.
- Deprecated `craft\commerce\events\ProductTypeEvent`. `CraftCms\Commerce\Catalog\Events\ProductTypeEvent` should be used instead.
- Deprecated `craft\commerce\events\PurchaseVariantEvent`. `CraftCms\Commerce\Catalog\Events\PurchaseVariantEvent` should be used instead.
- Removed `craft\commerce\records\ProductTypeShippingCategory` as it was unused; `CraftCms\Commerce\Shipping\ShippingCategories` manages the `commerce_producttypes_shippingcategories` pivot table directly through the query builder.
- Removed `craft\commerce\records\ProductTypeTaxCategory` as it was unused; `CraftCms\Commerce\Tax\TaxCategories` manages the `commerce_producttypes_taxcategories` pivot table directly through the query builder.
- Added `CraftCms\Commerce\Catalog\Conditions\ProductCondition`, `ProductTypeConditionRule`, `ProductVariantSearchConditionRule`, `ProductVariantSkuConditionRule`, `ProductVariantStockConditionRule`, `ProductVariantPriceConditionRule`, and `ProductVariantInventoryTrackedConditionRule`.
- Added `CraftCms\Commerce\Catalog\Conditions\VariantCondition`, `VariantProductConditionRule`, and `VariantConditionRule`.
- Added `CraftCms\Commerce\Catalog\Conditions\CatalogPricingRuleProductCondition`, `CatalogPricingRuleVariantCondition`, and `CatalogPricingRuleVariantConditionRule`.
- Deprecated `craft\commerce\elements\conditions\products\ProductCondition`, `ProductTypeConditionRule`, `ProductVariantSearchConditionRule`, `ProductVariantSkuConditionRule`, `ProductVariantStockConditionRule`, `ProductVariantPriceConditionRule`, `ProductVariantInventoryTrackedConditionRule`, and `CatalogPricingRuleProductCondition`. The `CraftCms\Commerce\Catalog\Conditions` equivalents should be used instead.
- Deprecated `craft\commerce\elements\conditions\variants\VariantCondition`, `ProductConditionRule`, `VariantConditionRule`, `CatalogPricingRuleVariantCondition`, and `CatalogPricingRuleVariantConditionRule`. The `CraftCms\Commerce\Catalog\Conditions` equivalents should be used instead.
- Removed `craft\commerce\elements\conditions\products\ProductVariantHasUnlimitedStockConditionRule`, deprecated since 5.0.0 and already unregistered from `ProductCondition::selectableConditionRules()`.
- Added `CraftCms\Commerce\Catalog\Actions\SetDefaultVariant`.
- Added `CraftCms\Commerce\Catalog\FieldLayoutElements\ProductTitleField`, `VariantTitleField`, and `VariantsField`.
- Added `CraftCms\Commerce\Catalog\Fields\Products` and `Variants`.
- Deprecated `craft\commerce\elements\actions\SetDefaultVariant`. `CraftCms\Commerce\Catalog\Actions\SetDefaultVariant` should be used instead.
- Deprecated `craft\commerce\fieldlayoutelements\ProductTitleField`, `VariantTitleField`, and `VariantsField`. The `CraftCms\Commerce\Catalog\FieldLayoutElements` equivalents should be used instead.
- Deprecated `craft\commerce\fields\Products` and `Variants`. The `CraftCms\Commerce\Catalog\Fields` equivalents should be used instead.
- Removed `craft\commerce\linktypes\Product`, superseded by `CraftCms\Commerce\Catalog\LinkTypes\ProductLinkType`.
- Added `CraftCms\Commerce\Catalog\Jobs\ResaveProductVariantsJob`, a native Laravel `ShouldQueue` job.
- Removed `craft\commerce\queue\jobs\ResaveProductVariants`. `CraftCms\Commerce\Catalog\Jobs\ResaveProductVariantsJob` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\ProductsController`. `CraftCms\Commerce\Http\Controllers\ProductsController` should be used instead.
- Removed `craft\commerce\controllers\VariantsController`. `CraftCms\Commerce\Http\Controllers\VariantsController` should be used instead.
- Removed `craft\commerce\controllers\ProductTypesController`. `CraftCms\Commerce\Http\Controllers\Settings\ProductTypesController` should be used instead.

### Catalog Pricing

- Added `CraftCms\Commerce\CatalogPricing\CatalogPricing`.
- Added `CraftCms\Commerce\CatalogPricing\CatalogPricingRules`.
- Added `CraftCms\Commerce\CatalogPricing\Records\CatalogPricingQueue`.
- Added `CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule`.
- Added `CraftCms\Commerce\Catalog\Models\CatalogPricing`.
- Added `CraftCms\Commerce\Catalog\Models\CatalogPricingRule`.
- Added `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition`.
- Added `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule`.
- Added `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule`.
- Added `CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface`.
- Deprecated `craft\commerce\services\CatalogPricing`. `CraftCms\Commerce\CatalogPricing\CatalogPricing` should be used instead.
- Deprecated `craft\commerce\services\CatalogPricingRules`. `CraftCms\Commerce\CatalogPricing\CatalogPricingRules` should be used instead.
- Deprecated `craft\commerce\models\CatalogPricing`. `CraftCms\Commerce\Catalog\Models\CatalogPricing` should be used instead.
- Deprecated `craft\commerce\models\CatalogPricingRule`. `CraftCms\Commerce\Catalog\Models\CatalogPricingRule` should be used instead.
- Deprecated `craft\commerce\records\CatalogPricingRule`. `CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule` should be used instead.
- Deprecated `craft\commerce\elements\conditions\purchasables\CatalogPricingCondition`. `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition` should be used instead.
- Deprecated `craft\commerce\elements\conditions\purchasables\CatalogPricingPurchasableConditionRule`. `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule` should be used instead.
- Deprecated `craft\commerce\elements\conditions\purchasables\CatalogPricingCustomerConditionRule`. `CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCustomerConditionRule` should be used instead.
- Deprecated `craft\commerce\base\CatalogPricingConditionRuleInterface`. `CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface` should be used instead.
- Removed `craft\commerce\records\CatalogPricing` as it was unused.
- Removed `craft\commerce\records\CatalogPricingRuleUser` as it was unused; the `commerce_catalog_pricing_rules_users` pivot table is managed directly through the query builder.
- Removed `craft\commerce\records\CatalogPricingRule`. `CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule` should be used instead.
- Removed `craft\commerce\records\CatalogPricingQueue`. `CraftCms\Commerce\CatalogPricing\Records\CatalogPricingQueue` should be used instead.
- `CraftCms\Commerce\Catalog\Models\CatalogPricingRule` now uses `CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerCondition`, `CraftCms\Commerce\Catalog\Conditions\CatalogPricingRuleProductCondition`, `CatalogPricingRuleVariantCondition`, and `CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition`.
- Added `CraftCms\Commerce\CatalogPricing\Jobs\CatalogPricingJob`, a native Laravel `ShouldQueue` job.
- Removed `craft\commerce\queue\jobs\CatalogPricing`. `CraftCms\Commerce\CatalogPricing\Jobs\CatalogPricingJob` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\CatalogPricingRulesController`. `CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingRulesController` should be used instead.
- Removed `craft\commerce\controllers\CatalogPricingController`. `CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingController` should be used instead.

### Console

- Added `CraftCms\Commerce\Console\Commands\ExampleTemplates\ExampleTemplatesCommand` (`commerce:example-templates`).
- Added `CraftCms\Commerce\Console\Commands\Gateways\GatewaysListCommand` (`commerce:gateways:list`).
- Added `CraftCms\Commerce\Console\Commands\Gateways\GatewaysWebhookUrlCommand` (`commerce:gateways:webhook-url`).
- Added `CraftCms\Commerce\Console\Commands\PricingCatalog\PricingCatalogGenerateCommand` (`commerce:pricing-catalog:generate`).
- Added `CraftCms\Commerce\Console\Commands\ResetData\ResetDataCommand` (`commerce:reset-data`).
- Added `CraftCms\Commerce\Console\Commands\TransferCustomerData\TransferCustomerDataCommand` (`commerce:transfer-customer-data`).
- Removed `craft\commerce\console\controllers\ExampleTemplatesController`, `craft\commerce\console\controllers\GatewaysController`, `craft\commerce\console\controllers\PricingCatalogController`, `craft\commerce\console\controllers\ResetDataController`, and `craft\commerce\console\controllers\TransferCustomerDataController`. Their legacy `commerce/*` CLI routes still work as command aliases (e.g. `craft commerce/gateways/list`).
- Removed `craft\commerce\console\Controller`.

### Controllers

- Removed `craft\commerce\controllers\BaseController`, `craft\commerce\controllers\BaseCpController`, `craft\commerce\controllers\BaseAdminController`, and `craft\commerce\controllers\BaseFrontEndController`.
- Removed `craft\commerce\controllers\BaseStoreManagementController`.
- Removed `craft\commerce\controllers\BaseTaxSettingsController`.
- Removed `craft\commerce\controllers\BaseShippingSettingsController`.
- Removed `craft\commerce\controllers\SettingsController`. `CraftCms\Commerce\Http\Controllers\Settings\SettingsController` should be used instead.
- Removed `craft\commerce\controllers\PromotionsController`. The `commerce/promotions` URL now redirects to `commerce/promotions/sales` via a plain route closure.

### Customers

- Added `CraftCms\Commerce\Customer\Customers`.
- Added `CraftCms\Commerce\Customer\Records\Customer`.
- Added `CraftCms\Commerce\Customer\Customers::afterSaveUserHandler()` and `afterSaveAddressHandler()`, listening for `CraftCms\Cms\Element\Events\ElementSaved` to persist primary billing/shipping addresses, primary payment sources, and order email syncing.
- Deprecated `craft\commerce\services\Customers`. `CraftCms\Commerce\Customer\Customers` should be used instead.
- Removed `craft\commerce\behaviors\CustomerBehavior`. `User::getPrimaryBillingAddressId()`, `getPrimaryShippingAddressId()`, `getPrimaryPaymentSourceId()`, `getActiveCarts()`, `getInactiveCarts()`, and `getOrders()` are provided via a `Illuminate\Support\Traits\Macroable` macro instead.
- Removed `craft\commerce\behaviors\CustomerAddressBehavior`. `Address::getIsPrimaryBilling()` and `getIsPrimaryShipping()` are provided via a `Illuminate\Support\Traits\Macroable` macro instead.
- Added `CraftCms\Commerce\Customer\Fields\IsPrimaryBillingField`, `IsPrimaryShippingField`, `PrimaryBillingAddressIdField`, and `PrimaryShippingAddressIdField` — opt-in, read-only custom field types (no content-column storage; the value is always recomputed from the macros above) that admins can add to the `Address`/`User` field layout to expose these values via `toArray()`/GraphQL, which macro-based attributes don't otherwise flow through. Closes the "can't be serialized" half of the `defineFields()`/`defineRules()` design gap noted during the Plugin.php bootstrap migration.
- Removed `craft\commerce\records\Customer`. `CraftCms\Commerce\Customer\Records\Customer` should be used instead.
- Added `CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition`, `HasOrdersConditionRule`, `SignedInConditionRule`, `DiscountGroupConditionRule`, `ShippingMethodCustomerCondition`, `ShippingRuleCustomerCondition`, `CatalogPricingRuleCustomerCondition`, and `CatalogPricingRuleCustomerConditionRule`.
- Deprecated `craft\commerce\elements\conditions\customers\DiscountCustomerCondition`, `HasOrdersConditionRule`, `SignedInConditionRule`, `ShippingMethodCustomerCondition`, `ShippingRuleCustomerCondition`, `CatalogPricingRuleCustomerCondition`, and `CatalogPricingRuleCustomerConditionRule`. The `CraftCms\Commerce\Customer\Conditions` equivalents should be used instead.
- Deprecated `craft\commerce\elements\conditions\users\DiscountGroupConditionRule`. `CraftCms\Commerce\Customer\Conditions\DiscountGroupConditionRule` should be used instead.
- Added `CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings`.
- Deprecated `craft\commerce\fieldlayoutelements\UserAddressSettings`. `CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings` should be used instead.

### Dashboard & Widgets

- Added `CraftCms\Commerce\Stats\Stat`.
- Added `CraftCms\Commerce\Stats\Contracts\StatInterface`.
- Added `CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait` trait.
- Added `CraftCms\Commerce\Stats\AverageOrderTotal`.
- Added `CraftCms\Commerce\Stats\NewCustomers`.
- Added `CraftCms\Commerce\Stats\RepeatCustomers`.
- Added `CraftCms\Commerce\Stats\TopCustomers`.
- Added `CraftCms\Commerce\Stats\TopProductTypes`.
- Added `CraftCms\Commerce\Stats\TopPurchasables`.
- Added `CraftCms\Commerce\Stats\TopProducts`.
- Added `CraftCms\Commerce\Stats\TotalOrders`.
- Added `CraftCms\Commerce\Stats\TotalOrdersByCountry`.
- Added `CraftCms\Commerce\Stats\TotalRevenue`.
- Added `CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal`.
- Added `CraftCms\Commerce\Dashboard\Widgets\NewCustomers`.
- Added `CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TopCustomers`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TopProductTypes`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TopPurchasables`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TopProducts`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TotalOrders`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry`.
- Added `CraftCms\Commerce\Dashboard\Widgets\TotalRevenue`.
- Added `CraftCms\Commerce\Dashboard\Widgets\Orders`.
- Deprecated `craft\commerce\stats\AverageOrderTotal`. `CraftCms\Commerce\Stats\AverageOrderTotal` should be used instead.
- Deprecated `craft\commerce\stats\NewCustomers`. `CraftCms\Commerce\Stats\NewCustomers` should be used instead.
- Deprecated `craft\commerce\stats\RepeatCustomers`. `CraftCms\Commerce\Stats\RepeatCustomers` should be used instead.
- Deprecated `craft\commerce\stats\TopCustomers`. `CraftCms\Commerce\Stats\TopCustomers` should be used instead.
- Deprecated `craft\commerce\stats\TopProductTypes`. `CraftCms\Commerce\Stats\TopProductTypes` should be used instead.
- Deprecated `craft\commerce\stats\TopPurchasables`. `CraftCms\Commerce\Stats\TopPurchasables` should be used instead.
- Deprecated `craft\commerce\stats\TopProducts`. `CraftCms\Commerce\Stats\TopProducts` should be used instead.
- Deprecated `craft\commerce\stats\TotalOrders`. `CraftCms\Commerce\Stats\TotalOrders` should be used instead.
- Deprecated `craft\commerce\stats\TotalOrdersByCountry`. `CraftCms\Commerce\Stats\TotalOrdersByCountry` should be used instead.
- Deprecated `craft\commerce\stats\TotalRevenue`. `CraftCms\Commerce\Stats\TotalRevenue` should be used instead.
- Deprecated `craft\commerce\base\StatInterface`. `CraftCms\Commerce\Stats\Contracts\StatInterface` should be used instead.
- `CraftCms\Commerce\Stats\Stat` and its subclasses now build their queries entirely through the Laravel query builder, using `tpetry/laravel-query-expressions` for cross-database SQL differences instead of manual driver checks.
- `CraftCms\Commerce\Stats\Stat` now implements `CraftCms\Commerce\Store\Contracts\HasStoreInterface`, matching its legacy counterpart (`StoreTrait` already satisfied the interface's contract; the `implements` clause itself had been dropped).
- Added `CraftCms\Commerce\Support\Expressions\LocalTimestamp`, `DateOnly`, `MonthKey`, and `Round` query expressions.
- Deprecated `craft\commerce\widgets\AverageOrderTotal`. `CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal` should be used instead.
- Deprecated `craft\commerce\widgets\NewCustomers`. `CraftCms\Commerce\Dashboard\Widgets\NewCustomers` should be used instead.
- Deprecated `craft\commerce\widgets\RepeatCustomers`. `CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers` should be used instead.
- Deprecated `craft\commerce\widgets\TopCustomers`. `CraftCms\Commerce\Dashboard\Widgets\TopCustomers` should be used instead.
- Deprecated `craft\commerce\widgets\TopProductTypes`. `CraftCms\Commerce\Dashboard\Widgets\TopProductTypes` should be used instead.
- Deprecated `craft\commerce\widgets\TopPurchasables`. `CraftCms\Commerce\Dashboard\Widgets\TopPurchasables` should be used instead.
- Deprecated `craft\commerce\widgets\TopProducts`. `CraftCms\Commerce\Dashboard\Widgets\TopProducts` should be used instead.
- Deprecated `craft\commerce\widgets\TotalOrders`. `CraftCms\Commerce\Dashboard\Widgets\TotalOrders` should be used instead.
- Deprecated `craft\commerce\widgets\TotalOrdersByCountry`. `CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry` should be used instead.
- Deprecated `craft\commerce\widgets\TotalRevenue`. `CraftCms\Commerce\Dashboard\Widgets\TotalRevenue` should be used instead.
- Deprecated `craft\commerce\widgets\Orders`. `CraftCms\Commerce\Dashboard\Widgets\Orders` should be used instead.
- Deprecated `craft\commerce\base\Stat`. `CraftCms\Commerce\Stats\Stat` should be used instead.
- Deprecated `craft\commerce\base\StatWidgetTrait`. `CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait` should be used instead.
- Deprecated `craft\commerce\base\StatTrait`. Its properties are now declared directly on `CraftCms\Commerce\Stats\Stat`.

### Email

- Added `CraftCms\Commerce\Email\Emails`.
- Added `CraftCms\Commerce\Email\Records\Email`.
- Added `CraftCms\Commerce\Email\Models\Email`.
- Added `CraftCms\Commerce\Email\Exceptions\EmailException`.
- Added `CraftCms\Commerce\Email\Events\EmailEvent`.
- Added `CraftCms\Commerce\Email\Events\MailEvent`.
- Added `CraftCms\Commerce\Email\Jobs\SendEmailJob`, a native Laravel `ShouldQueue` job.
- Removed `craft\commerce\queue\jobs\SendEmail`. `CraftCms\Commerce\Email\Jobs\SendEmailJob` should be used instead.
- Deprecated `craft\commerce\services\Emails`. `CraftCms\Commerce\Email\Emails` should be used instead.
- Deprecated `craft\commerce\models\Email`. `CraftCms\Commerce\Email\Models\Email` should be used instead.
- Deprecated `craft\commerce\errors\EmailException`. `CraftCms\Commerce\Email\Exceptions\EmailException` should be used instead.
- Deprecated `craft\commerce\events\EmailEvent`. `CraftCms\Commerce\Email\Events\EmailEvent` should be used instead.
- Deprecated `craft\commerce\events\MailEvent`. `CraftCms\Commerce\Email\Events\MailEvent` should be used instead.
- Removed `craft\commerce\records\Email`. `CraftCms\Commerce\Email\Records\Email` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\EmailsController`. `CraftCms\Commerce\Http\Controllers\Settings\EmailsController` should be used instead.
- Removed `craft\commerce\controllers\EmailPreviewController`. `CraftCms\Commerce\Http\Controllers\EmailPreviewController` should be used instead.

### Pdf

- Added `CraftCms\Commerce\Pdf\Pdfs`.
- Added `CraftCms\Commerce\Pdf\Records\Pdf`.
- Added `CraftCms\Commerce\Pdf\Models\Pdf`.
- Added `CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter`, replacing the per-action `yii\filters\RateLimiter` behavior used to throttle PDF download challenge requests.
- Added `CraftCms\Commerce\Pdf\Events\PdfEvent`.
- Added `CraftCms\Commerce\Pdf\Events\PdfRenderEvent`.
- Added `CraftCms\Commerce\Pdf\Events\PdfRenderOptionsEvent`.
- Deprecated `craft\commerce\services\Pdfs`. `CraftCms\Commerce\Pdf\Pdfs` should be used instead.
- Deprecated `craft\commerce\models\Pdf`. `CraftCms\Commerce\Pdf\Models\Pdf` should be used instead.
- Deprecated `craft\commerce\events\PdfEvent`. `CraftCms\Commerce\Pdf\Events\PdfEvent` should be used instead.
- Deprecated `craft\commerce\events\PdfRenderEvent`. `CraftCms\Commerce\Pdf\Events\PdfRenderEvent` should be used instead.
- Deprecated `craft\commerce\events\PdfRenderOptionsEvent`. `CraftCms\Commerce\Pdf\Events\PdfRenderOptionsEvent` should be used instead.
- Removed `craft\commerce\records\Pdf`. `CraftCms\Commerce\Pdf\Records\Pdf` should be used instead.
- Updated dompdf/dompdf to ^3.1.6 (from ^2.0.2).

#### Controllers

- Removed `craft\commerce\controllers\PdfsController`. `CraftCms\Commerce\Http\Controllers\Settings\PdfsController` should be used instead.

### Formulas

- Added `CraftCms\Commerce\Formula\Formulas`.
- Deprecated `craft\commerce\services\Formulas`. `CraftCms\Commerce\Formula\Formulas` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\FormulasController`. `CraftCms\Commerce\Http\Controllers\FormulasController` should be used instead.

### GraphQL

- Added `CraftCms\Commerce\Gql\Arguments\Elements\Product`.
- Added `CraftCms\Commerce\Gql\Arguments\Elements\Variant`.
- Added `CraftCms\Commerce\Gql\Interfaces\Elements\Product`.
- Added `CraftCms\Commerce\Gql\Interfaces\Elements\Variant`.
- Added `CraftCms\Commerce\Gql\Queries\Product`.
- Added `CraftCms\Commerce\Gql\Queries\Variant`.
- Added `CraftCms\Commerce\Gql\Resolvers\Elements\Product`.
- Added `CraftCms\Commerce\Gql\Resolvers\Elements\Variant`.
- Added `CraftCms\Commerce\Gql\Types\Elements\Product`.
- Added `CraftCms\Commerce\Gql\Types\Elements\Variant`.
- Added `CraftCms\Commerce\Gql\Types\Generators\ProductType`.
- Added `CraftCms\Commerce\Gql\Types\Generators\VariantType`.
- Added `CraftCms\Commerce\Gql\Types\Input\IntFalse`.
- Added `CraftCms\Commerce\Gql\Types\Input\Product`.
- Added `CraftCms\Commerce\Gql\Types\Input\Variant`.
- Added `CraftCms\Commerce\Gql\Types\SaleType`.
- Deprecated `craft\commerce\gql\arguments\elements\Product`. `CraftCms\Commerce\Gql\Arguments\Elements\Product` should be used instead.
- Deprecated `craft\commerce\gql\arguments\elements\Variant`. `CraftCms\Commerce\Gql\Arguments\Elements\Variant` should be used instead.
- Deprecated `craft\commerce\gql\interfaces\elements\Product`. `CraftCms\Commerce\Gql\Interfaces\Elements\Product` should be used instead.
- Deprecated `craft\commerce\gql\interfaces\elements\Variant`. `CraftCms\Commerce\Gql\Interfaces\Elements\Variant` should be used instead.
- Deprecated `craft\commerce\gql\queries\Product`. `CraftCms\Commerce\Gql\Queries\Product` should be used instead.
- Deprecated `craft\commerce\gql\queries\Variant`. `CraftCms\Commerce\Gql\Queries\Variant` should be used instead.
- Deprecated `craft\commerce\gql\resolvers\elements\Product`. `CraftCms\Commerce\Gql\Resolvers\Elements\Product` should be used instead.
- Deprecated `craft\commerce\gql\resolvers\elements\Variant`. `CraftCms\Commerce\Gql\Resolvers\Elements\Variant` should be used instead.
- Deprecated `craft\commerce\gql\types\elements\Product`. `CraftCms\Commerce\Gql\Types\Elements\Product` should be used instead.
- Deprecated `craft\commerce\gql\types\elements\Variant`. `CraftCms\Commerce\Gql\Types\Elements\Variant` should be used instead.
- Deprecated `craft\commerce\gql\types\generators\ProductType`. `CraftCms\Commerce\Gql\Types\Generators\ProductType` should be used instead.
- Deprecated `craft\commerce\gql\types\generators\VariantType`. `CraftCms\Commerce\Gql\Types\Generators\VariantType` should be used instead.
- Deprecated `craft\commerce\gql\types\input\IntFalse`. `CraftCms\Commerce\Gql\Types\Input\IntFalse` should be used instead.
- Deprecated `craft\commerce\gql\types\input\Product`. `CraftCms\Commerce\Gql\Types\Input\Product` should be used instead.
- Deprecated `craft\commerce\gql\types\input\Variant`. `CraftCms\Commerce\Gql\Types\Input\Variant` should be used instead.
- Deprecated `craft\commerce\gql\types\SaleType`. `CraftCms\Commerce\Gql\Types\SaleType` should be used instead.
- Deprecated `craft\commerce\helpers\Gql`. `CraftCms\Commerce\Helpers\Gql` should be used instead.

### Helpers

- Added `CraftCms\Commerce\Helpers\Cp`.
- Added `CraftCms\Commerce\Helpers\Currency`.
- Added `CraftCms\Commerce\Helpers\Locale`.
- Added `CraftCms\Commerce\Helpers\Localization`.
- Added `CraftCms\Commerce\Helpers\Order`.
- Added `CraftCms\Commerce\Helpers\ProductQuery`.
- Added `CraftCms\Commerce\Helpers\ProjectConfigData`.
- Added `CraftCms\Commerce\Helpers\Purchasable`.
- Deprecated `craft\commerce\helpers\Cp`. `CraftCms\Commerce\Helpers\Cp` should be used instead.
- Deprecated `craft\commerce\helpers\Currency`. `CraftCms\Commerce\Helpers\Currency` should be used instead.
- Deprecated `craft\commerce\helpers\Locale`. `CraftCms\Commerce\Helpers\Locale` should be used instead.
- Deprecated `craft\commerce\helpers\Localization`. `CraftCms\Commerce\Helpers\Localization` should be used instead.
- Deprecated `craft\commerce\helpers\Order`. `CraftCms\Commerce\Helpers\Order` should be used instead.
- Deprecated `craft\commerce\helpers\ProductQuery`. `CraftCms\Commerce\Helpers\ProductQuery` should be used instead.
- Deprecated `craft\commerce\helpers\ProjectConfigData`. `CraftCms\Commerce\Helpers\ProjectConfigData` should be used instead.
- Deprecated `craft\commerce\helpers\Purchasable`. `CraftCms\Commerce\Helpers\Purchasable` should be used instead.

### Inventory

- Added `CraftCms\Commerce\Inventory\Inventory`.
- Added `CraftCms\Commerce\Inventory\InventoryLocations`.
- Added `CraftCms\Commerce\Inventory\Records\InventoryItem`.
- Added `CraftCms\Commerce\Inventory\Records\InventoryLocation`.
- Added `CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection`.
- Added `CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryLocation`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryManualMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryCommittedMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryFulfillMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryRestockMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryTransferMovement`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryLocationDeactivatedMovement`.
- Added `CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryItem`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryFulfillmentLevel`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryLevel`.
- Added `CraftCms\Commerce\Inventory\Models\InventoryTransaction`.
- Added `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevel`.
- Added `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevelInTransfer`.
- Added `CraftCms\Commerce\Inventory\Concerns\InventoryItemTrait`.
- Added `CraftCms\Commerce\Inventory\Concerns\InventoryLocationTrait`.
- Added `CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface`.
- Added `CraftCms\Commerce\Inventory\Enums\InventoryTransactionType` enum.
- Added `CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType` enum.
- Added `CraftCms\Commerce\Inventory\Events\InventoryMovementEvent`.
- Added `CraftCms\Commerce\Inventory\Events\UpdateInventoryLevelEvent`.
- Deprecated `craft\commerce\services\Inventory`. `CraftCms\Commerce\Inventory\Inventory` should be used instead.
- Deprecated `craft\commerce\services\InventoryLocations`. `CraftCms\Commerce\Inventory\InventoryLocations` should be used instead.
- Deprecated `craft\commerce\collections\InventoryMovementCollection`. `CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection` should be used instead.
- Deprecated `craft\commerce\collections\UpdateInventoryLevelCollection`. `CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection` should be used instead.
- Deprecated `craft\commerce\models\InventoryLocation`. `CraftCms\Commerce\Inventory\Models\InventoryLocation` should be used instead.
- Deprecated `craft\commerce\base\InventoryMovement`. `CraftCms\Commerce\Inventory\Models\InventoryMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryManualMovement`. `CraftCms\Commerce\Inventory\Models\InventoryManualMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryCommittedMovement`. `CraftCms\Commerce\Inventory\Models\InventoryCommittedMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryFulfillMovement`. `CraftCms\Commerce\Inventory\Models\InventoryFulfillMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryRestockMovement`. `CraftCms\Commerce\Inventory\Models\InventoryRestockMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryTransferMovement`. `CraftCms\Commerce\Inventory\Models\InventoryTransferMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\InventoryLocationDeactivatedMovement`. `CraftCms\Commerce\Inventory\Models\InventoryLocationDeactivatedMovement` should be used instead.
- Deprecated `craft\commerce\models\inventory\DeactivateInventoryLocation`. `CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation` should be used instead.
- Deprecated `craft\commerce\models\InventoryItem`. `CraftCms\Commerce\Inventory\Models\InventoryItem` should be used instead.
- Deprecated `craft\commerce\models\InventoryFulfillmentLevel`. `CraftCms\Commerce\Inventory\Models\InventoryFulfillmentLevel` should be used instead.
- Deprecated `craft\commerce\models\InventoryLevel`. `CraftCms\Commerce\Inventory\Models\InventoryLevel` should be used instead.
- Deprecated `craft\commerce\models\InventoryTransaction`. `CraftCms\Commerce\Inventory\Models\InventoryTransaction` should be used instead.
- Deprecated `craft\commerce\models\inventory\UpdateInventoryLevel`. `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevel` should be used instead.
- Deprecated `craft\commerce\models\inventory\UpdateInventoryLevelInTransfer`. `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevelInTransfer` should be used instead.
- Deprecated `craft\commerce\base\InventoryItemTrait`. `CraftCms\Commerce\Inventory\Concerns\InventoryItemTrait` should be used instead.
- Deprecated `craft\commerce\base\InventoryLocationTrait`. `CraftCms\Commerce\Inventory\Concerns\InventoryLocationTrait` should be used instead.
- Deprecated `craft\commerce\base\InventoryMovementInterface`. `CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface` should be used instead.
- Deprecated `craft\commerce\enums\InventoryTransactionType`. `CraftCms\Commerce\Inventory\Enums\InventoryTransactionType` should be used instead.
- Deprecated `craft\commerce\enums\InventoryUpdateQuantityType`. `CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType` should be used instead.
- Deprecated `craft\commerce\events\InventoryMovementEvent`. `CraftCms\Commerce\Inventory\Events\InventoryMovementEvent` should be used instead.
- Deprecated `craft\commerce\events\UpdateInventoryLevelEvent`. `CraftCms\Commerce\Inventory\Events\UpdateInventoryLevelEvent` should be used instead.
- Removed `craft\commerce\records\InventoryItem`. `CraftCms\Commerce\Inventory\Records\InventoryItem` should be used instead.
- Removed `craft\commerce\records\InventoryLocation`. `CraftCms\Commerce\Inventory\Records\InventoryLocation` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\InventoryController`. `CraftCms\Commerce\Http\Controllers\InventoryController` should be used instead.
- Removed `craft\commerce\controllers\InventoryLocationsController`. `CraftCms\Commerce\Http\Controllers\InventoryLocationsController` should be used instead.

### Orders

- Added `CraftCms\Commerce\Order\Elements\Order`.
- Added `CraftCms\Commerce\Order\Queries\OrderQuery`.
- Added `CraftCms\Commerce\Order\Models\Order`.
- Added `CraftCms\Commerce\Order\Models\OrderStatus`.
- Added `CraftCms\Commerce\Order\Models\OrderAdjustment`.
- Added `CraftCms\Commerce\Order\Models\OrderNotice`.
- Added `CraftCms\Commerce\Order\Models\OrderHistory`.
- Added `CraftCms\Commerce\Order\Models\LineItemStatus`.
- Added `CraftCms\Commerce\Order\Records\OrderHistory`.
- Added `CraftCms\Commerce\Order\Records\OrderAdjustment`.
- Added `CraftCms\Commerce\Order\Records\LineItemStatus`.
- Added `CraftCms\Commerce\Order\Records\OrderStatus`.
- Added `CraftCms\Commerce\Order\Records\OrderNotice`.
- Added `CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException`.
- Added `CraftCms\Commerce\Order\Exceptions\CurrencyException`.
- Added `CraftCms\Commerce\Order\Exceptions\LineItemNotFoundException`.
- Added `CraftCms\Commerce\Order\Exceptions\OrderStatusException`.
- Added `CraftCms\Commerce\Order\Exceptions\LineItemException`.
- Added `CraftCms\Commerce\Order\LineItem\Data\LineItem`.
- Added `CraftCms\Commerce\Order\LineItem\Models\LineItem`.
- Added `CraftCms\Commerce\Order\LineItem\LineItems`.
- Added `CraftCms\Commerce\Order\LineItem\Enums\LineItemType` enum.
- Added `CraftCms\Commerce\Order\Orders`.
- Added `CraftCms\Commerce\Order\Carts`.
- Added `CraftCms\Commerce\Order\OrderNotices`.
- Added `CraftCms\Commerce\Order\OrderHistories`.
- Added `CraftCms\Commerce\Order\OrderAdjustments`.
- Added `CraftCms\Commerce\Order\OrderStatuses`.
- Added `CraftCms\Commerce\Order\LineItemStatuses`.
- Added `CraftCms\Commerce\Order\Adjuster\Tax`.
- Added `CraftCms\Commerce\Order\Adjuster\Shipping`.
- Added `CraftCms\Commerce\Order\Adjuster\Discount`.
- Added `CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface`.
- Added `CraftCms\Commerce\Order\Adjuster\AdjusterTypes`, a `CraftCms\Cms\Component\TypeRegistry` for registering order adjuster types.
- Added `CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes`, a `CraftCms\Cms\Component\TypeRegistry` for registering adjuster types that should be treated as discounts.
- Deprecated `craft\commerce\services\OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS`. `CraftCms\Commerce\Order\Adjuster\AdjusterTypes::register()` should be used instead.
- Deprecated `craft\commerce\services\OrderAdjustments::EVENT_REGISTER_DISCOUNT_ADJUSTERS`. `CraftCms\Commerce\Order\Adjuster\DiscountAdjusterTypes::register()` should be used instead.
- Added `CraftCms\Commerce\Order\Exporters\Expanded`.
- Added `CraftCms\Commerce\Order\Exporters\LineItemExport`.
- Added `CraftCms\Commerce\Order\Exporters\OrderExport`.
- Added `CraftCms\Commerce\Http\Controllers\Concerns\HasCartArray`, a trait shared between `CartController` and `PaymentsController` for building a cart's array representation.
- Added `CraftCms\Commerce\Http\RateLimiters\CartRateLimiter` and `CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter`.
- Added `CraftCms\Commerce\Order\Events\AddLineItemEvent`.
- Added `CraftCms\Commerce\Order\Events\CartEvent`.
- Added `CraftCms\Commerce\Order\Events\CartPurgeEvent`.
- Added `CraftCms\Commerce\Order\Events\DefaultLineItemStatusEvent`.
- Added `CraftCms\Commerce\Order\Events\DefaultOrderStatusEvent`.
- Added `CraftCms\Commerce\Order\Events\LineItemEvent`.
- Added `CraftCms\Commerce\Order\Events\ModifyCartInfoEvent`.
- Added `CraftCms\Commerce\Order\Events\OrderLineItemsRefreshEvent`.
- Added `CraftCms\Commerce\Order\Events\OrderNoticeEvent`.
- Added `CraftCms\Commerce\Order\Events\OrderStatusEmailsEvent`.
- Added `CraftCms\Commerce\Order\Events\OrderStatusEvent`.
- Added `CraftCms\Commerce\Order\Events\PurgeAddressesEvent`.
- Deprecated `craft\commerce\elements\Order`. `CraftCms\Commerce\Order\Elements\Order` should be used instead.
- Deprecated `craft\commerce\elements\db\OrderQuery`. `CraftCms\Commerce\Order\Queries\OrderQuery` should be used instead.
- Deprecated `craft\commerce\records\Order`. `CraftCms\Commerce\Order\Models\Order` should be used instead.
- Deprecated `craft\commerce\models\OrderStatus`. `CraftCms\Commerce\Order\Models\OrderStatus` should be used instead.
- Deprecated `craft\commerce\models\OrderAdjustment`. `CraftCms\Commerce\Order\Models\OrderAdjustment` should be used instead.
- Deprecated `craft\commerce\models\OrderNotice`. `CraftCms\Commerce\Order\Models\OrderNotice` should be used instead.
- Deprecated `craft\commerce\models\OrderHistory`. `CraftCms\Commerce\Order\Models\OrderHistory` should be used instead.
- Deprecated `craft\commerce\models\LineItemStatus`. `CraftCms\Commerce\Order\Models\LineItemStatus` should be used instead.
- Deprecated `craft\commerce\models\LineItem`. `CraftCms\Commerce\Order\LineItem\Data\LineItem` should be used instead.
- Deprecated `craft\commerce\records\LineItem`. `CraftCms\Commerce\Order\LineItem\Models\LineItem` should be used instead.
- Deprecated `craft\commerce\services\LineItems`. `CraftCms\Commerce\Order\LineItem\LineItems` should be used instead.
- Deprecated `craft\commerce\enums\LineItemType`. `CraftCms\Commerce\Order\LineItem\Enums\LineItemType` should be used instead.
- Deprecated `craft\commerce\services\Orders`. `CraftCms\Commerce\Order\Orders` should be used instead.
- Deprecated `craft\commerce\services\Carts`. `CraftCms\Commerce\Order\Carts` should be used instead.
- Deprecated `craft\commerce\services\OrderNotices`. `CraftCms\Commerce\Order\OrderNotices` should be used instead.
- Deprecated `craft\commerce\services\OrderHistories`. `CraftCms\Commerce\Order\OrderHistories` should be used instead.
- Deprecated `craft\commerce\services\OrderAdjustments`. `CraftCms\Commerce\Order\OrderAdjustments` should be used instead.
- Deprecated `craft\commerce\services\OrderStatuses`. `CraftCms\Commerce\Order\OrderStatuses` should be used instead.
- Deprecated `craft\commerce\services\LineItemStatuses`. `CraftCms\Commerce\Order\LineItemStatuses` should be used instead.
- Deprecated `craft\commerce\adjusters\Tax`. `CraftCms\Commerce\Order\Adjuster\Tax` should be used instead.
- Deprecated `craft\commerce\adjusters\Shipping`. `CraftCms\Commerce\Order\Adjuster\Shipping` should be used instead.
- Deprecated `craft\commerce\adjusters\Discount`. `CraftCms\Commerce\Order\Adjuster\Discount` should be used instead.
- Deprecated `craft\commerce\base\AdjusterInterface`. `CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface` should be used instead.
- Deprecated `craft\commerce\exports\Expanded`. `CraftCms\Commerce\Order\Exporters\Expanded` should be used instead.
- Deprecated `craft\commerce\exports\LineItemExport`. `CraftCms\Commerce\Order\Exporters\LineItemExport` should be used instead.
- Deprecated `craft\commerce\exports\OrderExport`. `CraftCms\Commerce\Order\Exporters\OrderExport` should be used instead.
- Deprecated `craft\commerce\errors\OrderAdjustmentNotFoundException`. `CraftCms\Commerce\Order\Exceptions\OrderAdjustmentNotFoundException` should be used instead.
- Deprecated `craft\commerce\errors\CurrencyException`. `CraftCms\Commerce\Order\Exceptions\CurrencyException` should be used instead.
- Deprecated `craft\commerce\errors\LineItemNotFoundException`. `CraftCms\Commerce\Order\Exceptions\LineItemNotFoundException` should be used instead.
- Deprecated `craft\commerce\errors\OrderStatusException`. `CraftCms\Commerce\Order\Exceptions\OrderStatusException` should be used instead.
- Deprecated `craft\commerce\errors\LineItemException`. `CraftCms\Commerce\Order\Exceptions\LineItemException` should be used instead.
- Deprecated `craft\commerce\events\AddLineItemEvent`. `CraftCms\Commerce\Order\Events\AddLineItemEvent` should be used instead.
- Deprecated `craft\commerce\events\CartEvent`. `CraftCms\Commerce\Order\Events\CartEvent` should be used instead.
- Deprecated `craft\commerce\events\CartPurgeEvent`. `CraftCms\Commerce\Order\Events\CartPurgeEvent` should be used instead.
- Deprecated `craft\commerce\events\DefaultLineItemStatusEvent`. `CraftCms\Commerce\Order\Events\DefaultLineItemStatusEvent` should be used instead.
- Deprecated `craft\commerce\events\DefaultOrderStatusEvent`. `CraftCms\Commerce\Order\Events\DefaultOrderStatusEvent` should be used instead.
- Deprecated `craft\commerce\events\LineItemEvent`. `CraftCms\Commerce\Order\Events\LineItemEvent` should be used instead.
- Deprecated `craft\commerce\events\ModifyCartInfoEvent`. `CraftCms\Commerce\Order\Events\ModifyCartInfoEvent` should be used instead.
- Deprecated `craft\commerce\events\OrderLineItemsRefreshEvent`. `CraftCms\Commerce\Order\Events\OrderLineItemsRefreshEvent` should be used instead.
- Deprecated `craft\commerce\events\OrderNoticeEvent`. `CraftCms\Commerce\Order\Events\OrderNoticeEvent` should be used instead.
- Deprecated `craft\commerce\events\OrderStatusEmailsEvent`. `CraftCms\Commerce\Order\Events\OrderStatusEmailsEvent` should be used instead.
- Deprecated `craft\commerce\events\OrderStatusEvent`. `CraftCms\Commerce\Order\Events\OrderStatusEvent` should be used instead.
- Deprecated `craft\commerce\events\PurgeAddressesEvent`. `CraftCms\Commerce\Order\Events\PurgeAddressesEvent` should be used instead.
- Removed `craft\commerce\records\OrderHistory`. `CraftCms\Commerce\Order\Records\OrderHistory` should be used instead.
- Removed `craft\commerce\records\OrderAdjustment`. `CraftCms\Commerce\Order\Records\OrderAdjustment` should be used instead.
- Removed `craft\commerce\records\LineItemStatus`. `CraftCms\Commerce\Order\Records\LineItemStatus` should be used instead.
- Removed `craft\commerce\records\OrderStatus`. `CraftCms\Commerce\Order\Records\OrderStatus` should be used instead.
- Removed `craft\commerce\records\OrderNotice`. `CraftCms\Commerce\Order\Records\OrderNotice` should be used instead.
- Removed `LineItem::getSaleAmount()`, `refreshFromPurchasable()`, and `populateFromPurchasable()` as they had no remaining call sites.
- Removed `LineItems::createLineItem()` as it had no remaining call sites.
- Removed `Carts::getCartName()`. The `cartCookie['name']` config setting should be used instead.
- Added `CraftCms\Commerce\Order\Conditions\OrderCondition`, `CompletedConditionRule`, `CouponCodeConditionRule`, `CustomerConditionRule`, `DateOrderedConditionRule`, `HasAdminNoticesConditionRule`, `PaidConditionRule`, `HasPurchasableConditionRule`, `ContainsPurchasablesConditionRule`, `OrderStatusConditionRule`, `OrderSiteConditionRule`, `PaymentGatewayConditionRule`, `ReferenceConditionRule`, `ShippingMethodConditionRule`, `ShippingAddressZoneConditionRule`, `DiscountedItemSubtotalConditionRule`, `ItemSubtotalConditionRule`, `ItemTotalConditionRule`, `TotalConditionRule`, `TotalDiscountConditionRule`, `TotalPaidConditionRule`, `TotalPriceConditionRule`, `TotalQtyConditionRule`, `TotalTaxConditionRule`, and `TotalWeightConditionRule`.
- Added `CraftCms\Commerce\Order\Conditions\OrderTextValuesAttributeConditionRule`, `OrderValuesAttributeConditionRule`, and `OrderCurrencyValuesAttributeConditionRule`.
- Added `CraftCms\Commerce\Order\Conditions\DiscountOrderCondition`, `GatewayOrderCondition`, `ShippingMethodOrderCondition`, and `ShippingRuleOrderCondition`.
- Deprecated `craft\commerce\elements\conditions\orders\*`. The `CraftCms\Commerce\Order\Conditions` equivalents should be used instead.
- Added `CraftCms\Commerce\Order\Actions\CopyLoadCartUrl`, `DownloadOrderPdfAction`, and `UpdateOrderStatus`.
- Deprecated `craft\commerce\elements\actions\CopyLoadCartUrl`, `DownloadOrderPdfAction`, and `UpdateOrderStatus`. The `CraftCms\Commerce\Order\Actions` equivalents should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\OrdersController`. `CraftCms\Commerce\Http\Controllers\OrdersController` should be used instead.
- Removed `craft\commerce\controllers\CartController`. `CraftCms\Commerce\Http\Controllers\CartController` should be used instead.
- Removed `craft\commerce\controllers\OrderStatusesController`. `CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController` should be used instead.
- Removed `craft\commerce\controllers\LineItemStatusesController`. `CraftCms\Commerce\Http\Controllers\Settings\LineItemStatusesController` should be used instead.
- Removed `craft\commerce\controllers\UserOrdersController`. `CraftCms\Commerce\Http\Controllers\UserOrdersController` should be used instead.
- Removed `craft\commerce\controllers\OrderSettingsController`. `CraftCms\Commerce\Http\Controllers\Settings\OrderSettingsController` should be used instead.
- Removed `craft\commerce\controllers\DownloadsController`. `CraftCms\Commerce\Http\Controllers\DownloadsController` should be used instead.

### Payments

- Added `CraftCms\Commerce\Payment\Transactions`.
- Added `CraftCms\Commerce\Payment\PaymentSources`.
- Added `CraftCms\Commerce\Payment\Gateway\Gateways`.
- Added `CraftCms\Commerce\Payment\Payments`.
- Added `CraftCms\Commerce\Payment\Webhooks`.
- Added `CraftCms\Commerce\Payment\Currencies`.
- Added `CraftCms\Commerce\Payment\PaymentCurrencies`.
- Added `CraftCms\Commerce\Payment\Records\Transaction`.
- Added `CraftCms\Commerce\Payment\Records\PaymentSource`.
- Added `CraftCms\Commerce\Payment\Gateway\Records\Gateway`.
- Added `CraftCms\Commerce\Payment\Records\PaymentCurrency`.
- Added `CraftCms\Commerce\Payment\Models\Transaction`.
- Added `CraftCms\Commerce\Payment\Models\PaymentSource`.
- Added `CraftCms\Commerce\Payment\Models\PaymentCurrency`.
- Added `CraftCms\Commerce\Payment\Forms\BasePaymentForm`.
- Added `CraftCms\Commerce\Payment\Forms\OffsitePaymentForm`.
- Added `CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm`.
- Added `CraftCms\Commerce\Payment\Forms\DummyPaymentForm`.
- Added `CraftCms\Commerce\Payment\Gateway\Responses\Dummy`.
- Added `CraftCms\Commerce\Payment\Gateway\Responses\Manual`.
- Added `CraftCms\Commerce\Payment\Exceptions\PaymentException`.
- Added `CraftCms\Commerce\Payment\Exceptions\PaymentSourceException`.
- Added `CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException`.
- Added `CraftCms\Commerce\Payment\Exceptions\RefundException`.
- Added `CraftCms\Commerce\Payment\Exceptions\TransactionException`.
- Added `CraftCms\Commerce\Payment\Gateway\Exceptions\GatewayException`.
- Added `CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface`.
- Added `CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface`.
- Added `CraftCms\Commerce\Payment\Gateway\GatewayTypes`, a `CraftCms\Cms\Component\TypeRegistry` for registering gateway types.
- Deprecated `craft\commerce\services\Gateways::EVENT_REGISTER_GATEWAY_TYPES`. `CraftCms\Commerce\Payment\Gateway\GatewayTypes::register()` should be used instead.
- Added `CraftCms\Commerce\Payment\Gateway\Gateway`.
- Added `CraftCms\Commerce\Payment\Gateway\Types\Dummy`.
- Added `CraftCms\Commerce\Payment\Gateway\Types\Manual`.
- Added `CraftCms\Commerce\Payment\Gateway\Types\MissingGateway`.
- Added `CraftCms\Commerce\Helpers\PaymentForm`.
- Added `CraftCms\Commerce\Payment\Events\PaymentCurrencyRateEvent`.
- Added `CraftCms\Commerce\Payment\Events\PaymentSourceEvent`.
- Added `CraftCms\Commerce\Payment\Events\ProcessPaymentEvent`.
- Added `CraftCms\Commerce\Payment\Events\RefundTransactionEvent`.
- Added `CraftCms\Commerce\Payment\Events\TransactionEvent`.
- Added `CraftCms\Commerce\Payment\Events\UpdatePrimaryPaymentSourceEvent`.
- Added `CraftCms\Commerce\Payment\Events\WebhookEvent`.
- Deprecated `craft\commerce\services\Transactions`. `CraftCms\Commerce\Payment\Transactions` should be used instead.
- Deprecated `craft\commerce\services\PaymentSources`. `CraftCms\Commerce\Payment\PaymentSources` should be used instead.
- Deprecated `craft\commerce\services\Gateways`. `CraftCms\Commerce\Payment\Gateway\Gateways` should be used instead.
- Deprecated `craft\commerce\base\Gateway`. `CraftCms\Commerce\Payment\Gateway\Gateway` should be used instead.
- Deprecated `craft\commerce\base\GatewayTrait`. Its properties and methods are now part of `CraftCms\Commerce\Payment\Gateway\Gateway`.
- Deprecated `craft\commerce\gateways\Dummy`. `CraftCms\Commerce\Payment\Gateway\Types\Dummy` should be used instead.
- Deprecated `craft\commerce\gateways\Manual`. `CraftCms\Commerce\Payment\Gateway\Types\Manual` should be used instead.
- Deprecated `craft\commerce\gateways\MissingGateway`. `CraftCms\Commerce\Payment\Gateway\Types\MissingGateway` should be used instead.
- Deprecated `craft\commerce\helpers\PaymentForm`. `CraftCms\Commerce\Helpers\PaymentForm` should be used instead.
- Deprecated `craft\commerce\services\Payments`. `CraftCms\Commerce\Payment\Payments` should be used instead.
- Deprecated `craft\commerce\services\Webhooks`. `CraftCms\Commerce\Payment\Webhooks` should be used instead.
- Deprecated `craft\commerce\services\Currencies`. `CraftCms\Commerce\Payment\Currencies` should be used instead.
- Deprecated `craft\commerce\services\PaymentCurrencies`. `CraftCms\Commerce\Payment\PaymentCurrencies` should be used instead.
- Deprecated `craft\commerce\models\Transaction`. `CraftCms\Commerce\Payment\Models\Transaction` should be used instead.
- Deprecated `craft\commerce\models\PaymentSource`. `CraftCms\Commerce\Payment\Models\PaymentSource` should be used instead.
- Deprecated `craft\commerce\models\PaymentCurrency`. `CraftCms\Commerce\Payment\Models\PaymentCurrency` should be used instead.
- Deprecated `craft\commerce\models\payments\BasePaymentForm`. `CraftCms\Commerce\Payment\Forms\BasePaymentForm` should be used instead.
- Deprecated `craft\commerce\models\payments\OffsitePaymentForm`. `CraftCms\Commerce\Payment\Forms\OffsitePaymentForm` should be used instead.
- Deprecated `craft\commerce\models\payments\CreditCardPaymentForm`. `CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm` should be used instead.
- Deprecated `craft\commerce\models\payments\DummyPaymentForm`. `CraftCms\Commerce\Payment\Forms\DummyPaymentForm` should be used instead.
- Deprecated `craft\commerce\models\responses\Dummy`. `CraftCms\Commerce\Payment\Gateway\Responses\Dummy` should be used instead.
- Deprecated `craft\commerce\models\responses\Manual`. `CraftCms\Commerce\Payment\Gateway\Responses\Manual` should be used instead.
- Deprecated `craft\commerce\errors\PaymentException`. `CraftCms\Commerce\Payment\Exceptions\PaymentException` should be used instead.
- Deprecated `craft\commerce\errors\PaymentSourceException`. `CraftCms\Commerce\Payment\Exceptions\PaymentSourceException` should be used instead.
- Deprecated `craft\commerce\errors\PaymentSourceCreatedLaterException`. `CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException` should be used instead.
- Deprecated `craft\commerce\errors\RefundException`. `CraftCms\Commerce\Payment\Exceptions\RefundException` should be used instead.
- Deprecated `craft\commerce\errors\TransactionException`. `CraftCms\Commerce\Payment\Exceptions\TransactionException` should be used instead.
- Deprecated `craft\commerce\errors\GatewayException`. `CraftCms\Commerce\Payment\Gateway\Exceptions\GatewayException` should be used instead.
- Deprecated `craft\commerce\base\GatewayInterface`. `CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface` should be used instead.
- Deprecated `craft\commerce\base\RequestResponseInterface`. `CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface` should be used instead.
- Deprecated `craft\commerce\events\PaymentSourceEvent`. `CraftCms\Commerce\Payment\Events\PaymentSourceEvent` should be used instead.
- Deprecated `craft\commerce\events\ProcessPaymentEvent`. `CraftCms\Commerce\Payment\Events\ProcessPaymentEvent` should be used instead.
- Deprecated `craft\commerce\events\RefundTransactionEvent`. `CraftCms\Commerce\Payment\Events\RefundTransactionEvent` should be used instead.
- Deprecated `craft\commerce\events\TransactionEvent`. `CraftCms\Commerce\Payment\Events\TransactionEvent` should be used instead.
- Deprecated `craft\commerce\events\UpdatePrimaryPaymentSourceEvent`. `CraftCms\Commerce\Payment\Events\UpdatePrimaryPaymentSourceEvent` should be used instead.
- Deprecated `craft\commerce\events\WebhookEvent`. `CraftCms\Commerce\Payment\Events\WebhookEvent` should be used instead.
- Removed `craft\commerce\records\Transaction`. `CraftCms\Commerce\Payment\Records\Transaction` should be used instead.
- Removed `craft\commerce\records\PaymentSource`. `CraftCms\Commerce\Payment\Records\PaymentSource` should be used instead.
- Removed `craft\commerce\records\Gateway`. `CraftCms\Commerce\Payment\Gateway\Records\Gateway` should be used instead.
- Removed `craft\commerce\records\PaymentCurrency`. `CraftCms\Commerce\Payment\Records\PaymentCurrency` should be used instead.
- Removed `Gateways::getGatewayOverrides()`. It depended on the `commerce-gateways.php` config-file override mechanism.
- Removed `Transactions::deleteTransaction()`. `deleteTransactionById()` should be used instead.
- Removed `PaymentCurrencies::convertCurrency()`. `convert()` or `convertAmount()` should be used instead.
- Widened `RefundTransactionEvent::$amount` to `?float` to allow `null` for a full refund.
- Widened `WebhookEvent::$response` to accept both `Illuminate\Http\Response` and `yii\web\Response`.
- `craft\commerce\base\Gateway` now uses `CraftCms\Commerce\Order\Conditions\GatewayOrderCondition` and `CraftCms\Commerce\Address\Conditions\GatewayAddressCondition`.

#### Controllers

- Removed `craft\commerce\controllers\PaymentsController`. `CraftCms\Commerce\Http\Controllers\PaymentsController` should be used instead.
- Removed `craft\commerce\controllers\PaymentSourcesController`. `CraftCms\Commerce\Http\Controllers\PaymentSourcesController` should be used instead.
- Removed `craft\commerce\controllers\WebhooksController`. `CraftCms\Commerce\Http\Controllers\WebhooksController` should be used instead.
- Removed `craft\commerce\controllers\Settings\GatewaysController`. `CraftCms\Commerce\Http\Controllers\Settings\GatewaysController` should be used instead.
- Removed `craft\commerce\controllers\PaymentCurrenciesController`. `CraftCms\Commerce\Http\Controllers\Settings\PaymentCurrenciesController` should be used instead.

### Promotions

- Added `CraftCms\Commerce\Promotion\Discounts`.
- Added `CraftCms\Commerce\Promotion\Sales`.
- Added `CraftCms\Commerce\Promotion\Coupons`.
- Added `CraftCms\Commerce\Promotion\Models\Discount`.
- Added `CraftCms\Commerce\Promotion\Models\Sale`.
- Added `CraftCms\Commerce\Promotion\Models\Coupon`.
- Added `CraftCms\Commerce\Promotion\Records\Discount`.
- Added `CraftCms\Commerce\Promotion\Records\DiscountCategory`.
- Added `CraftCms\Commerce\Promotion\Records\DiscountPurchasable`.
- Added `CraftCms\Commerce\Promotion\Records\CustomerDiscountUse`.
- Added `CraftCms\Commerce\Promotion\Records\EmailDiscountUse`.
- Added `CraftCms\Commerce\Promotion\Records\Sale`.
- Added `CraftCms\Commerce\Promotion\Records\SaleCategory`.
- Added `CraftCms\Commerce\Promotion\Records\SalePurchasable`.
- Added `CraftCms\Commerce\Promotion\Records\SaleUserGroup`.
- Added `CraftCms\Commerce\Promotion\Records\Coupon`.
- Added `CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsEvent`.
- Added `CraftCms\Commerce\Promotion\Events\DiscountEvent`.
- Added `CraftCms\Commerce\Promotion\Events\MatchLineItemEvent`.
- Added `CraftCms\Commerce\Promotion\Events\MatchOrderEvent`.
- Added `CraftCms\Commerce\Promotion\Events\SaleEvent`.
- Added `CraftCms\Commerce\Promotion\Events\SaleMatchEvent`.
- Deprecated `craft\commerce\services\Discounts`. `CraftCms\Commerce\Promotion\Discounts` should be used instead.
- Deprecated `craft\commerce\services\Sales`. `CraftCms\Commerce\Promotion\Sales` should be used instead.
- Deprecated `craft\commerce\services\Coupons`. `CraftCms\Commerce\Promotion\Coupons` should be used instead.
- Deprecated `craft\commerce\models\Discount`. `CraftCms\Commerce\Promotion\Models\Discount` should be used instead.
- Deprecated `craft\commerce\models\Sale`. `CraftCms\Commerce\Promotion\Models\Sale` should be used instead.
- Deprecated `craft\commerce\models\Coupon`. `CraftCms\Commerce\Promotion\Models\Coupon` should be used instead.
- Deprecated `craft\commerce\events\DiscountAdjustmentsEvent`. `CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsEvent` should be used instead.
- Deprecated `craft\commerce\events\DiscountEvent`. `CraftCms\Commerce\Promotion\Events\DiscountEvent` should be used instead.
- Deprecated `craft\commerce\events\MatchLineItemEvent`. `CraftCms\Commerce\Promotion\Events\MatchLineItemEvent` should be used instead.
- Deprecated `craft\commerce\events\MatchOrderEvent`. `CraftCms\Commerce\Promotion\Events\MatchOrderEvent` should be used instead.
- Deprecated `craft\commerce\events\SaleEvent`. `CraftCms\Commerce\Promotion\Events\SaleEvent` should be used instead.
- Deprecated `craft\commerce\events\SaleMatchEvent`. `CraftCms\Commerce\Promotion\Events\SaleMatchEvent` should be used instead.
- Removed `craft\commerce\records\DiscountCategory`. `CraftCms\Commerce\Promotion\Records\DiscountCategory` should be used instead.
- Removed `craft\commerce\records\DiscountPurchasable`. `CraftCms\Commerce\Promotion\Records\DiscountPurchasable` should be used instead.
- Removed `craft\commerce\records\CustomerDiscountUse`. `CraftCms\Commerce\Promotion\Records\CustomerDiscountUse` should be used instead.
- Removed `craft\commerce\records\EmailDiscountUse`. `CraftCms\Commerce\Promotion\Records\EmailDiscountUse` should be used instead.
- Removed `craft\commerce\records\Sale`. `CraftCms\Commerce\Promotion\Records\Sale` should be used instead.
- Removed `craft\commerce\records\SaleCategory`. `CraftCms\Commerce\Promotion\Records\SaleCategory` should be used instead.
- Removed `craft\commerce\records\SalePurchasable`. `CraftCms\Commerce\Promotion\Records\SalePurchasable` should be used instead.
- Removed `craft\commerce\records\SaleUserGroup`. `CraftCms\Commerce\Promotion\Records\SaleUserGroup` should be used instead.
- Removed `craft\commerce\records\Discount`. `CraftCms\Commerce\Promotion\Records\Discount` should be used instead.
- Removed `craft\commerce\records\Coupon`. `CraftCms\Commerce\Promotion\Records\Coupon` should be used instead.
- Removed `craft\commerce\models\Discount::setExcludeOnSale()`/`getExcludeOnSale()` and the `excludeOnSale` shim. `Discount::$excludeOnPromotion` should be used instead.
- `CraftCms\Commerce\Promotion\Models\Discount` now uses `CraftCms\Commerce\Order\Conditions\DiscountOrderCondition`, `CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition`, and `CraftCms\Commerce\Address\Conditions\DiscountAddressCondition`.
- Added `CraftCms\Commerce\Address\Conditions\DiscountAddressCondition`, `ZoneAddressCondition`, `GatewayAddressCondition`, and `PostalCodeFormulaConditionRule`.
- Deprecated `craft\commerce\elements\conditions\addresses\DiscountAddressCondition`, `ZoneAddressCondition`, `GatewayAddressCondition`, and `PostalCodeFormulaConditionRule`. The `CraftCms\Commerce\Address\Conditions` equivalents should be used instead.
- Added `CraftCms\Commerce\Promotion\Actions\CreateDiscount` and `CreateSale`.
- Deprecated `craft\commerce\elements\actions\CreateDiscount` and `CreateSale`. The `CraftCms\Commerce\Promotion\Actions` equivalents should be used instead.
- `CraftCms\Commerce\Promotion\Models\Coupon::getRules()` now validates that `code` is unique (case-insensitively, against every coupon regardless of discount), replacing `craft\commerce\validators\CouponsValidator`.
- Removed `craft\commerce\validators\CouponsValidator`, which had no remaining references anywhere in the codebase.

#### Controllers

- Removed `craft\commerce\controllers\SalesController`. `CraftCms\Commerce\Http\Controllers\Settings\SalesController` should be used instead.
- Removed `craft\commerce\controllers\DiscountsController`. `CraftCms\Commerce\Http\Controllers\Settings\DiscountsController` should be used instead.

### Purchasables

- Added `CraftCms\Commerce\Purchasable\Elements\Purchasable`.
- Added `CraftCms\Commerce\Purchasable\Elements\Donation`.
- Added `CraftCms\Commerce\Purchasable\Models\Donation`.
- Added `CraftCms\Commerce\Purchasable\Models\PurchasableStore`.
- Added `CraftCms\Commerce\Purchasable\Queries\PurchasableQuery`.
- Added `CraftCms\Commerce\Purchasable\Queries\DonationQuery`.
- Added `CraftCms\Commerce\Purchasable\Records\Purchasable`.
- Added `CraftCms\Commerce\Purchasable\Records\PurchasableStore`.
- Added `CraftCms\Commerce\Purchasable\Validation\PurchasableRules`.
- Added `CraftCms\Commerce\Purchasable\Validation\DonationRules`.
- Added `CraftCms\Commerce\Purchasable\Purchasables`.
- Added `CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface`.
- Added `CraftCms\Commerce\Purchasable\Events\PurchasableAvailableEvent`.
- Added `CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesAllowedEvent`.
- Added `CraftCms\Commerce\Purchasable\Events\PurchasableShippableEvent`.
- Added `CraftCms\Commerce\Purchasable\PurchasableTypes`, a `CraftCms\Cms\Component\TypeRegistry` for registering purchasable element types.
- Deprecated `craft\commerce\services\Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES`. `CraftCms\Commerce\Purchasable\PurchasableTypes::register()` should be used instead.
- Deprecated `craft\commerce\base\Purchasable`. `CraftCms\Commerce\Purchasable\Elements\Purchasable` should be used instead.
- Deprecated `craft\commerce\elements\Donation`. `CraftCms\Commerce\Purchasable\Elements\Donation` should be used instead.
- Deprecated `craft\commerce\records\Donation`. `CraftCms\Commerce\Purchasable\Models\Donation` should be used instead.
- Deprecated `craft\commerce\models\PurchasableStore`. `CraftCms\Commerce\Purchasable\Models\PurchasableStore` should be used instead.
- Deprecated `craft\commerce\elements\db\DonationQuery`. `CraftCms\Commerce\Purchasable\Queries\DonationQuery` should be used instead.
- Deprecated `craft\commerce\services\Purchasables`. `CraftCms\Commerce\Purchasable\Purchasables` should be used instead.
- Deprecated `craft\commerce\base\PurchasableInterface`. `CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface` should be used instead.
- Deprecated `craft\commerce\events\PurchasableAvailableEvent`. `CraftCms\Commerce\Purchasable\Events\PurchasableAvailableEvent` should be used instead.
- Deprecated `craft\commerce\events\PurchasableOutOfStockPurchasesAllowedEvent`. `CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesAllowedEvent` should be used instead.
- Deprecated `craft\commerce\events\PurchasableShippableEvent`. `CraftCms\Commerce\Purchasable\Events\PurchasableShippableEvent` should be used instead.
- Removed `craft\commerce\records\Purchasable`. `CraftCms\Commerce\Purchasable\Records\Purchasable` should be used instead.
- Removed `craft\commerce\records\PurchasableStore`. `CraftCms\Commerce\Purchasable\Records\PurchasableStore` should be used instead.
- Removed `craft\commerce\elements\db\PurchasableQuery`. `CraftCms\Commerce\Purchasable\Queries\PurchasableQuery` should be used instead.
- Removed `craft\commerce\records\OrderStatusEmail` as it was unused.
- Added `CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule`, `PurchasableTypeConditionRule`, `SkuConditionRule`, `CatalogPricingRulePurchasableCategoryConditionRule`, and `CatalogPricingRulePurchasableCondition`.
- Deprecated `craft\commerce\elements\conditions\purchasables\PurchasableConditionRule`, `PurchasableTypeConditionRule`, `SkuConditionRule`, `CatalogPricingRulePurchasableCategoryConditionRule`, and `CatalogPricingRulePurchasableCondition`. The `CraftCms\Commerce\Purchasable\Conditions` equivalents should be used instead.
- Added `CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableSkuField`, `PurchasablePriceField`, `PurchasableStockField`, `PurchasableWeightField`, `PurchasableDimensionsField`, `PurchasableAllowedQtyField`, `PurchasableAvailableForPurchaseField`, `PurchasableFreeShippingField`, and `PurchasablePromotableField`.
- Deprecated `craft\commerce\fieldlayoutelements\PurchasableSkuField`, `PurchasablePriceField`, `PurchasableStockField`, `PurchasableWeightField`, `PurchasableDimensionsField`, `PurchasableAllowedQtyField`, `PurchasableAvailableForPurchaseField`, `PurchasableFreeShippingField`, and `PurchasablePromotableField`. The `CraftCms\Commerce\Purchasable\FieldLayoutElements` equivalents should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\DonationsController`. `CraftCms\Commerce\Http\Controllers\DonationsController` should be used instead.

### Shipping

- Added `CraftCms\Commerce\Shipping\ShippingMethods`.
- Added `CraftCms\Commerce\Shipping\ShippingRules`.
- Added `CraftCms\Commerce\Shipping\ShippingRuleCategories`.
- Added `CraftCms\Commerce\Shipping\ShippingCategories`.
- Added `CraftCms\Commerce\Shipping\ShippingZones`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingRule`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingMethod`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingMethodOption`.
- Added `CraftCms\Commerce\Shipping\Models\BaseShippingMethod`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingAddressZone`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingRuleCategory`.
- Added `CraftCms\Commerce\Shipping\Models\ShippingCategory`.
- Added `CraftCms\Commerce\Shipping\Records\ShippingZone`.
- Added `CraftCms\Commerce\Shipping\Records\ShippingMethod`.
- Added `CraftCms\Commerce\Shipping\Records\ShippingRule`.
- Added `CraftCms\Commerce\Shipping\Records\ShippingRuleCategory`.
- Added `CraftCms\Commerce\Shipping\Records\ShippingCategory`.
- Added `CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface`.
- Added `CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface`.
- Added `CraftCms\Commerce\Shipping\Exceptions\ShippingMethodException`.
- Added `CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent`.
- Deprecated `craft\commerce\services\ShippingMethods`. `CraftCms\Commerce\Shipping\ShippingMethods` should be used instead.
- Deprecated `craft\commerce\services\ShippingRules`. `CraftCms\Commerce\Shipping\ShippingRules` should be used instead.
- Deprecated `craft\commerce\services\ShippingRuleCategories`. `CraftCms\Commerce\Shipping\ShippingRuleCategories` should be used instead.
- Deprecated `craft\commerce\services\ShippingCategories`. `CraftCms\Commerce\Shipping\ShippingCategories` should be used instead.
- Deprecated `craft\commerce\services\ShippingZones`. `CraftCms\Commerce\Shipping\ShippingZones` should be used instead.
- Deprecated `craft\commerce\models\ShippingRule`. `CraftCms\Commerce\Shipping\Models\ShippingRule` should be used instead.
- Deprecated `craft\commerce\models\ShippingMethod`. `CraftCms\Commerce\Shipping\Models\ShippingMethod` should be used instead.
- Deprecated `craft\commerce\models\ShippingMethodOption`. `CraftCms\Commerce\Shipping\Models\ShippingMethodOption` should be used instead.
- Deprecated `craft\commerce\base\ShippingMethod`. `CraftCms\Commerce\Shipping\Models\BaseShippingMethod` should be used instead.
- Deprecated `craft\commerce\models\ShippingAddressZone`. `CraftCms\Commerce\Shipping\Models\ShippingAddressZone` should be used instead.
- Deprecated `craft\commerce\models\ShippingRuleCategory`. `CraftCms\Commerce\Shipping\Models\ShippingRuleCategory` should be used instead.
- Deprecated `craft\commerce\models\ShippingCategory`. `CraftCms\Commerce\Shipping\Models\ShippingCategory` should be used instead.
- Deprecated `craft\commerce\base\ShippingMethodInterface`. `CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface` should be used instead.
- Deprecated `craft\commerce\base\ShippingRuleInterface`. `CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface` should be used instead.
- Deprecated `craft\commerce\errors\ShippingMethodException`. `CraftCms\Commerce\Shipping\Exceptions\ShippingMethodException` should be used instead.
- Deprecated `craft\commerce\events\RegisterAvailableShippingMethodsEvent`. `CraftCms\Commerce\Shipping\Events\RegisterAvailableShippingMethodsEvent` should be used instead.
- Removed `craft\commerce\records\ShippingZone`. `CraftCms\Commerce\Shipping\Records\ShippingZone` should be used instead.
- Removed `craft\commerce\records\ShippingMethod`. `CraftCms\Commerce\Shipping\Records\ShippingMethod` should be used instead.
- Removed `craft\commerce\records\ShippingRule`. `CraftCms\Commerce\Shipping\Records\ShippingRule` should be used instead.
- Removed `craft\commerce\records\ShippingRuleCategory`. `CraftCms\Commerce\Shipping\Records\ShippingRuleCategory` should be used instead.
- Removed `craft\commerce\records\ShippingCategory`. `CraftCms\Commerce\Shipping\Records\ShippingCategory` should be used instead.
- `CraftCms\Commerce\Shipping\Models\ShippingRule` and `BaseShippingMethod` now use `CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition`, `ShippingMethodOrderCondition`, `CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition`, and `ShippingMethodCustomerCondition`.
- `CraftCms\Commerce\Base\Zone` and `ZoneInterface` now use `CraftCms\Commerce\Address\Conditions\ZoneAddressCondition`.

#### Controllers

- Removed `craft\commerce\controllers\ShippingZonesController`. `CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController` should be used instead.
- Removed `craft\commerce\controllers\ShippingMethodsController`. `CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController` should be used instead.
- Removed `craft\commerce\controllers\ShippingRulesController`. `CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController` should be used instead.
- Removed `craft\commerce\controllers\ShippingCategoriesController`. `CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController` should be used instead.

### Stores

- Added `CraftCms\Commerce\Store\Stores`.
- Added `CraftCms\Commerce\Store\StoreSettings`.
- Added `CraftCms\Commerce\Store\Models\Store`.
- Added `CraftCms\Commerce\Store\Models\StoreSettings`.
- Added `CraftCms\Commerce\Store\Models\SiteStore`.
- Added `CraftCms\Commerce\Store\Records\Store`.
- Added `CraftCms\Commerce\Store\Records\SiteStore`.
- Added `CraftCms\Commerce\Store\Records\StoreSettings`.
- Added `CraftCms\Commerce\Store\Concerns\StoreTrait`.
- Added `CraftCms\Commerce\Store\Contracts\HasStoreInterface`.
- Added `CraftCms\Commerce\Store\Exceptions\StoreNotFoundException`.
- Added `CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen`, a trait shared by store-scoped settings controllers for their CP screen chrome.
- Added `CraftCms\Commerce\Store\Events\DeleteStoreEvent`.
- Added `CraftCms\Commerce\Store\Events\StoreEvent`.
- Deprecated `craft\commerce\services\Stores`. `CraftCms\Commerce\Store\Stores` should be used instead.
- Removed `craft\commerce\behaviors\StoreBehavior`. `Site::getStore()` is provided via a `Illuminate\Support\Traits\Macroable` macro instead.
- Deprecated `craft\commerce\services\StoreSettings`. `CraftCms\Commerce\Store\StoreSettings` should be used instead.
- Deprecated `craft\commerce\models\Store`. `CraftCms\Commerce\Store\Models\Store` should be used instead.
- Deprecated `craft\commerce\models\StoreSettings`. `CraftCms\Commerce\Store\Models\StoreSettings` should be used instead.
- Deprecated `craft\commerce\models\SiteStore`. `CraftCms\Commerce\Store\Models\SiteStore` should be used instead.
- Deprecated `craft\commerce\base\StoreTrait`. `CraftCms\Commerce\Store\Concerns\StoreTrait` should be used instead.
- Deprecated `craft\commerce\base\HasStoreInterface`. `CraftCms\Commerce\Store\Contracts\HasStoreInterface` should be used instead.
- Deprecated `craft\commerce\errors\StoreNotFoundException`. `CraftCms\Commerce\Store\Exceptions\StoreNotFoundException` should be used instead.
- Deprecated `craft\commerce\events\DeleteStoreEvent`. `CraftCms\Commerce\Store\Events\DeleteStoreEvent` should be used instead.
- Deprecated `craft\commerce\events\StoreEvent`. `CraftCms\Commerce\Store\Events\StoreEvent` should be used instead.
- Removed `craft\commerce\services\Store`. `CraftCms\Commerce\Store\Stores` should be used instead.
- Removed `craft\commerce\models\Store::setCountries()`, `getCountries()`, `getCountriesList()`, `getAdministrativeAreasListByCountryCode()`, and `getMarketAddressCondition()`. `Store::getSettings()` (returning `CraftCms\Commerce\Store\Models\StoreSettings`) should be used instead.
- Removed `craft\commerce\records\SiteStore`. `CraftCms\Commerce\Store\Records\SiteStore` should be used instead.
- Removed `craft\commerce\records\StoreSettings`. `CraftCms\Commerce\Store\Records\StoreSettings` should be used instead.
- Removed `craft\commerce\records\Store`. `CraftCms\Commerce\Store\Records\Store` should be used instead.
- Removed `craft\commerce\base\StoreRecordTrait` as it was unused.

#### Controllers

- Removed `craft\commerce\controllers\StoreManagementController`. `CraftCms\Commerce\Http\Controllers\Settings\StoreManagementController` should be used instead.
- Removed `craft\commerce\controllers\StoresController`. `CraftCms\Commerce\Http\Controllers\Settings\StoresController` should be used instead.

### Subscriptions

> [!WARNING]
> Subscription and billing-plan functionality has been removed from Craft Commerce entirely — there is no `Subscription` element type, no subscription field layout, and gateways can no longer implement subscription support. The `commerce_subscriptions`, `commerce_plans`, and related database tables are **not** dropped, so existing data is preserved for a future standalone migration path; only the application code (elements, services, records, models, forms, events, controllers, CP screens, and the gateway subscription interface) has been removed.

- Removed `craft\commerce\elements\Subscription`, its element query, condition support, and deletion blocker. No replacement.
- Removed `craft\commerce\services\Subscriptions` and `CraftCms\Commerce\Subscription\Subscriptions`. No replacement.
- Removed `craft\commerce\services\Plans` and `CraftCms\Commerce\Subscription\Plans`. No replacement.
- Removed `craft\commerce\records\Subscription` and `CraftCms\Commerce\Subscription\Records\Subscription`. No replacement.
- Removed `craft\commerce\records\Plan` and `CraftCms\Commerce\Subscription\Records\Plan`. No replacement.
- Removed `craft\commerce\base\Plan`, `craft\commerce\base\PlanInterface`, `craft\commerce\base\PlanTrait`, and `CraftCms\Commerce\Subscription\Contracts\PlanInterface`. No replacement.
- Removed `craft\commerce\base\SubscriptionGateway` and `craft\commerce\base\SubscriptionGatewayInterface` — gateways can no longer declare subscription support. `craft\commerce\gateways\Dummy` now extends `craft\commerce\base\Gateway` directly.
- Removed `craft\commerce\base\SubscriptionResponseInterface` and `CraftCms\Commerce\Subscription\Contracts\SubscriptionResponseInterface`. No replacement.
- Removed `craft\commerce\models\subscriptions\DummyPlan` and `CraftCms\Commerce\Subscription\Models\DummyPlan`. No replacement.
- Removed `craft\commerce\models\subscriptions\SubscriptionPayment` and `CraftCms\Commerce\Subscription\Models\SubscriptionPayment`. No replacement.
- Removed `craft\commerce\models\subscriptions\CancelSubscriptionForm`, `SubscriptionForm`, `SwitchPlansForm`, and their `CraftCms\Commerce\Subscription\Forms\*` equivalents. No replacement.
- Removed `craft\commerce\models\responses\DummySubscriptionResponse` and `CraftCms\Commerce\Subscription\Responses\DummySubscriptionResponse`. No replacement.
- Removed `craft\commerce\errors\SubscriptionException` and `CraftCms\Commerce\Subscription\Exceptions\SubscriptionException`. `Payments::refund()` now throws `CraftCms\Commerce\Payment\Exceptions\RefundException` for unsupported-refund cases, which it always should have (fixes a bug where the wrong exception class was thrown).
- Removed `craft\commerce\events\CancelSubscriptionEvent`, `CreateSubscriptionEvent`, `PlanEvent`, `SubscriptionEvent`, `SubscriptionPaymentEvent`, `SubscriptionSwitchPlansEvent`, and their `CraftCms\Commerce\Subscription\Events\*` equivalents. No replacement.
- Removed the `commerce-manageSubscriptions` and `commerce-manageSubscriptionPlans` permissions.
- Removed the "Subscriptions" and "Subscription Plans" Control Panel nav items, the "Subscriptions" tab on the Edit User screen, and the "Subscription Settings" section of the general settings page (including the `updateBillingDetailsUrl` setting and `Settings::VIEW_URI_SUBSCRIPTIONS` constant).
- Removed the `craft.commerce.subscriptions()` Twig variable. No replacement.
- Removed `Gateways::getAllSubscriptionGateways()` (both `craft\commerce\services\Gateways` and `CraftCms\Commerce\Payment\Gateway\Gateways`).

#### Controllers

- Removed `craft\commerce\controllers\SubscriptionsController` and `CraftCms\Commerce\Http\Controllers\SubscriptionsController`. No replacement.
- Removed `craft\commerce\controllers\PlansController` and `CraftCms\Commerce\Http\Controllers\Settings\PlansController`. No replacement.

### Tax

- Added `CraftCms\Commerce\Tax\TaxCategories`.
- Added `CraftCms\Commerce\Tax\TaxZones`.
- Added `CraftCms\Commerce\Tax\Records\TaxCategory`.
- Added `CraftCms\Commerce\Tax\Records\TaxZone`.
- Added `CraftCms\Commerce\Tax\Records\TaxRate`.
- Added `CraftCms\Commerce\Tax\Models\TaxRate`.
- Added `CraftCms\Commerce\Tax\Models\TaxAddressZone`.
- Added `CraftCms\Commerce\Tax\Models\TaxCategory`.
- Added `CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface`.
- Added `CraftCms\Commerce\Tax\Contracts\TaxEngineInterface`.
- Added `CraftCms\Commerce\Tax\Events\TaxEngineEvent`.
- Added `CraftCms\Commerce\Tax\Events\TaxIdValidatorsEvent`.
- Deprecated `craft\commerce\services\TaxCategories`. `CraftCms\Commerce\Tax\TaxCategories` should be used instead.
- Deprecated `craft\commerce\services\TaxZones`. `CraftCms\Commerce\Tax\TaxZones` should be used instead.
- Deprecated `craft\commerce\models\TaxRate`. `CraftCms\Commerce\Tax\Models\TaxRate` should be used instead.
- Deprecated `craft\commerce\models\TaxAddressZone`. `CraftCms\Commerce\Tax\Models\TaxAddressZone` should be used instead.
- Deprecated `craft\commerce\models\TaxCategory`. `CraftCms\Commerce\Tax\Models\TaxCategory` should be used instead.
- Deprecated `craft\commerce\base\TaxIdValidatorInterface`. `CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface` should be used instead.
- Deprecated `craft\commerce\base\TaxEngineInterface`. `CraftCms\Commerce\Tax\Contracts\TaxEngineInterface` should be used instead.
- Deprecated `craft\commerce\events\TaxEngineEvent`. `CraftCms\Commerce\Tax\Events\TaxEngineEvent` should be used instead.
- Deprecated `craft\commerce\events\TaxIdValidatorsEvent`. `CraftCms\Commerce\Tax\Events\TaxIdValidatorsEvent` should be used instead.
- Removed `craft\commerce\records\TaxZone`. `CraftCms\Commerce\Tax\Records\TaxZone` should be used instead.
- Removed `craft\commerce\records\TaxRate`. `CraftCms\Commerce\Tax\Records\TaxRate` should be used instead.
- Removed `craft\commerce\records\TaxCategory`. `CraftCms\Commerce\Tax\Records\TaxCategory` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\TaxZonesController`. `CraftCms\Commerce\Http\Controllers\Settings\TaxZonesController` should be used instead.
- Removed `craft\commerce\controllers\TaxCategoriesController`. `CraftCms\Commerce\Http\Controllers\Settings\TaxCategoriesController` should be used instead.
- Removed `craft\commerce\controllers\TaxRatesController`. `CraftCms\Commerce\Http\Controllers\Settings\TaxRatesController` should be used instead.

### Transfers

- Added `CraftCms\Commerce\Transfer\Elements\Transfer`.
- Added `CraftCms\Commerce\Transfer\Queries\TransferQuery`.
- Added `CraftCms\Commerce\Transfer\Conditions\TransferCondition`.
- Added `CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField`.
- Added `CraftCms\Commerce\Transfer\Transfers`.
- Added `CraftCms\Commerce\Transfer\Models\TransferDetail`.
- Added `CraftCms\Commerce\Transfer\Records\Transfer`.
- Added `CraftCms\Commerce\Transfer\Records\TransferDetail`.
- Added `CraftCms\Commerce\Transfer\Enums\TransferStatusType` enum.
- Deprecated `craft\commerce\elements\Transfer`. `CraftCms\Commerce\Transfer\Elements\Transfer` should be used instead.
- Deprecated `craft\commerce\elements\db\TransferQuery`. `CraftCms\Commerce\Transfer\Queries\TransferQuery` should be used instead.
- Deprecated `craft\commerce\elements\conditions\transfers\TransferCondition`. `CraftCms\Commerce\Transfer\Conditions\TransferCondition` should be used instead.
- Deprecated `craft\commerce\fieldlayoutelements\TransferManagementField`. `CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField` should be used instead.
- Deprecated `craft\commerce\services\Transfers`. `CraftCms\Commerce\Transfer\Transfers` should be used instead.
- Deprecated `craft\commerce\models\TransferDetail`. `CraftCms\Commerce\Transfer\Models\TransferDetail` should be used instead.
- Deprecated `craft\commerce\enums\TransferStatusType`. `CraftCms\Commerce\Transfer\Enums\TransferStatusType` should be used instead.
- Removed `craft\commerce\records\Transfer`. `CraftCms\Commerce\Transfer\Records\Transfer` should be used instead.
- Removed `craft\commerce\records\TransferDetail`. `CraftCms\Commerce\Transfer\Records\TransferDetail` should be used instead.

#### Controllers

- Removed `craft\commerce\controllers\TransfersController`. `CraftCms\Commerce\Http\Controllers\TransfersController` should be used instead.

### Users

- Removed `craft\commerce\controllers\UsersController`. `CraftCms\Commerce\Http\Controllers\Users\UsersController` should be used instead.
- The Commerce tab on the Edit User screen is now built from `CraftCms\Cms\Http\Controllers\Users\EditUserTrait`; Commerce listens for `CraftCms\Cms\User\Events\EditUserScreensResolving` instead of the removed `craft\controllers\UsersController::EVENT_DEFINE_EDIT_SCREENS`.

### Extensibility

- Added `CraftCms\Commerce\Plugin`, extending `CraftCms\Cms\Plugin\Plugin`. `craft\commerce\Plugin` now extends this class instead of `craft\base\Plugin`.
- Added `Plugin::getPermissions()`, exposing Commerce's permissions as `CraftCms\Cms\User\Data\Permission` objects, including a dynamic `commerce-viewProductType:{uid}` permission (with nested create/save/delete permissions) per product type.
- Added `Plugin::getCpNavItem()`, building the Commerce CP nav item and its permission-gated subnav via `CraftCms\Cms\Cp\Data\NavItem` instead of the legacy array-based `getCpNavItem()` override.
- Added `CraftCms\Commerce\Console\Commands\Resave\ResaveProductsCommand`, `ResaveVariantsCommand`, `ResaveOrdersCommand`, and `ResaveCartsCommand`, registered as `craft:resave:products`, `craft:resave:variants`, `craft:resave:orders`, and `craft:resave:carts` (also picked up automatically by `craft:resave:all`).
- Added `CraftCms\Commerce\Support\ObjectState`, a `WeakMap`-backed per-instance state store used by the `Site`/`User`/`Address` `Macroable` macros above.
- Added GraphQL schema component and eager-loadable field registration via `CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving` and `GqlEagerLoadableFieldsResolving`.
- Added garbage collection registration via `CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection`, purging incomplete carts, orphaned variants, and partial Donation/Order/Product/Variant/Transfer elements.
- Added `craft.commerce`, `craft.orders`, `craft.products`, and `craft.variants` Twig variables via `CraftCms\Cms\Twig\Variables\CraftVariable::macro()`.
- Added `Plugin::getDonation()`, reachable in Twig as `craft.commerce.getDonation()`, matching the legacy `craft\commerce\plugin\Variables::getDonation()` trait method.
- The `commerce/products/<productTypeHandle>/<id>`, `commerce/variants/<id>`, and `commerce/inventory/transfers/<id>` element-edit screens, previously Craft core's generic `elements/edit` action registered via a legacy `UrlManager` rule, are now registered directly in `routes/cp.php` against `CraftCms\Cms\Http\Controllers\Elements\EditElementController`.
- Registered Commerce's Twig extension via the `CraftCms\Cms\Support\Facades\Twig` facade.
- Added `CraftCms\Commerce\Order\Elements\Order::defineExporters()`, registering `CraftCms\Commerce\Order\Exporters\OrderExport` and `LineItemExport`.
- Added `CraftCms\Commerce\Base\Zone`.
- Added `CraftCms\Commerce\Base\ZoneInterface`.
- Added `CraftCms\Commerce\Database\Table`.
- Added `CraftCms\Commerce\Settings`.
- Added `CraftCms\Commerce\Exceptions\NotImplementedException`.
- Added `CraftCms\Commerce\Events\UpgradeEvent`.
- Added `CraftCms\Commerce\Twig\Extension`, replacing `craft\commerce\web\twig\Extension`. Registers the same `commerceCurrency`/`commercePaymentFormNamespace` filters and a `currentStore` global, now sourced from `CraftCms\Cms\Support\Facades\Sites::getCurrentSite()->getStore()` (the `Site::macro('getStore', ...)` registered in `Plugin::registerBehaviorMacros()`) instead of the removed `StoreBehavior`.
- Removed `craft\commerce\web\twig\CraftVariableBehavior` and its `EVENT_INIT` registration on the legacy `craft\web\twig\variables\CraftVariable`. It attached `craft.commerce`/`craft.orders`/`craft.products`/`craft.variants` to the legacy Yii2 `CraftVariable` via `attachBehavior()`, but the yii2-adapter's compatibility bridge only forwards legacy `getComponents()` entries into the new `CraftCms\Cms\Twig\Variables\CraftVariable`, not attached behaviors — so this had no effect on the live `craft` Twig global under Craft 6, which is already fully served by the `NewCraftVariable::macro(...)` registrations in `Plugin::registerVariableMacros()`. Verified via `TemplateManager::renderString()` that `craft.commerce`/`craft.orders()`/`craft.products()`/`craft.variants()` are unaffected by the removal.
- Deprecated `craft\commerce\base\Zone`. `CraftCms\Commerce\Base\Zone` should be used instead.
- Deprecated `craft\commerce\base\ZoneInterface`. `CraftCms\Commerce\Base\ZoneInterface` should be used instead.
- Deprecated `craft\commerce\db\Table`. `CraftCms\Commerce\Database\Table` should be used instead.
- Deprecated `craft\commerce\models\Settings`. `CraftCms\Commerce\Settings` should be used instead.
- Deprecated `craft\commerce\errors\NotImplementedException`. `CraftCms\Commerce\Exceptions\NotImplementedException` should be used instead.
- Deprecated `craft\commerce\events\UpgradeEvent`. `CraftCms\Commerce\Events\UpgradeEvent` should be used instead.
- Deprecated `craft\commerce\events\*`. Cancelable Commerce events (previously extending `craft\events\CancelableEvent`) now use the `CraftCms\Cms\Shared\Concerns\ValidatableEvent` trait instead.
- Removed `craft\commerce\web\twig\Extension`. `CraftCms\Commerce\Twig\Extension` should be used instead.
- Removed `craft\commerce\models\Settings::VIEW_URI_CUSTOMERS`, `VIEW_URI_PROMOTIONS`, `VIEW_URI_SHIPPING`, and `VIEW_URI_TAX` constants.
- Improved `craft\commerce\Plugin`'s `Plugin::getInstance()->getX()` service getters to be backed by a lazy-instantiate-and-cache trait rather than Yii2's component locator.

### System

- Removed the Commerce Yii2 debug panel and all related classes (`CommercePanel`, `DebugPanel` helper, `CommerceDebugPanelDataEvent`, and its Twig views) — the Yii2 debug module they relied on no longer exists in Craft CMS 6.
- Removed `src-yii2/etc/commands.php`, an unused "A&M quick commands" registration file with no references anywhere in the codebase.
- Removed `src-yii2/etc/currencies.php`, a static copy of moneyphp/money's currency data that was superseded by `CraftCms\Commerce\Payment\Currencies`, which now reads currency data directly from the `moneyphp/money` package.
- Deprecated `craft\commerce\base\Model`, an empty pass-through subclass of `craft\base\Model` with no consumers. `CraftCms\Cms\Component\Component` should be used instead, matching every other already-migrated Commerce model.
- Removed `craft\commerce\plugin\LegacyRoutingModule`. It existed purely so Yii2's `Module::createController()` could still find `craft\commerce\controllers\*`; that namespace has been empty since the console-controller and controller migrations, making the module dead code.
- Removed `craft\commerce\plugin\Variables`. Its one method, `getDonation()`, moved to `Plugin::getDonation()`.
- Removed the 6 loose point-release migrations previously ported to `database/migrations/` (product type permission renaming, the catalog pricing queue table, the orders `customerDeleted` column, the subscriptions `userId` foreign key cascade, order notices' `noticeType` column, and the `allVariants`→`variants` changedattributes repair). Commerce 6.0 only ships `database/migrations/Install.php` for fresh installs — schema changes among those six are already part of `Install.php`'s table definitions, and the other two were one-time data repairs for bugs that no longer exist in a fresh 6.0 schema.
- Fixed `CraftCms\Commerce\Transfer\Elements\Transfer::canView()`/`canSave()`/`canDelete()` — they checked a `commerce-manageTransfers` permission that was never registered (only `commerce-manageInventoryTransfers` is), so the permission-based access path was silently dead; they now check the correct permission key.
- Fixed a `TypeError` that could be thrown from any legacy `craft\commerce\services\*` delegation method whose return/param type is a `craft\commerce\{models,elements}\*` class alias (e.g. `Stores::getPrimaryStore()`, `Orders::getOrderById()`) — on the *first* call to such a method in a process, PHP's return-type check can lose a race against the alias's own `class_alias()` autoload (the alias file loads during the check, but the check doesn't see it as satisfied until the next call), throwing `Return value must be of type ?craft\commerce\models\X, CraftCms\Commerce\...\X returned` even though both names refer to the identical class. All 22 affected `src-yii2/services/*` wrappers now type-hint against the `CraftCms\Commerce\*` class directly instead of the legacy alias, which sidesteps the race entirely (both names being the same class, this is a no-op for callers still type-hinting against the legacy alias).
- Removed `craft\commerce\behaviors\StoreLocationBehavior`, dead code since before this migration — its `Address::EVENT_AFTER_SAVE`/`EVENT_AUTHORIZE_VIEW` attachment point (a Commerce 4-era `StoreLocationController`) no longer exists. Its authorization logic already lives in `StoreSettings::authorizeStoreLocationView()`/`authorizeStoreLocationEdit()` (wired via `ElementAuthorizing`), and its address-sync logic is now explicit in `StoreManagementController::save()`.
- Removed `craft\commerce\behaviors\ValidateOrganizationTaxIdBehavior`, superseded by the Commerce 5.0 redesign that moved VAT validation from a generic `Address` rule to `Order::afterValidate()` (gated per-store by `getValidateOrganizationTaxIdAsVatId()`), already fully ported.
- Removed `craft\commerce\behaviors\CurrencyAttributeBehavior`. Its consuming classes now provide explicit `get*AsCurrency()` getters directly (`Order`, `LineItem`, `Purchasable` — covers `Variant`/`Donation` via inheritance — `Product`, `Transaction`, `CatalogPricing`, `ShippingMethodOption`, `OrderAdjustment`), matching the pattern already established for `Order`/`LineItem`/`Purchasable`/`Product` during earlier migration stages.
- Fixed `CraftCms\Commerce\Payment\Models\Transaction`: added back `getAmountAsCurrency()`, `getPaymentAmountAsCurrency()`, and `getRefundableAmountAsCurrency()`, dropped from the legacy `CurrencyAttributeBehavior` in an earlier migration stage without updating `OrdersController::getTransactionsWithLevelsTableArray()` or the example templates, both of which call them — this was throwing `UnknownPropertyException` whenever the order-edit CP screen loaded an order with a payment transaction.
- Fixed `CraftCms\Commerce\Catalog\Models\CatalogPricing`: added back `getPriceAsCurrency()`, dropped from the legacy `CurrencyAttributeBehavior` without updating `src-yii2/templates/prices/_table.twig` (Settings → Product Pricing), which calls it — this was throwing `UnknownPropertyException` on that screen.
- Added `getPriceAsCurrency()` to `CraftCms\Commerce\Shipping\Models\ShippingMethodOption` and `getAmountAsCurrency()` to `CraftCms\Commerce\Order\Models\OrderAdjustment` (the latter used repeatedly in the shipped `example-templates/`), closing out the rest of the `CurrencyAttributeBehavior` removal — third-party templates/plugins could still call these via the legacy behavior's magic `__call`, independent of whether Commerce's own code used them.

### Translations

- Moved `src-yii2/translations/` to a top-level `lang/` directory (e.g. `lang/en/commerce.php`, `lang/de/commerce.php`), matching the Laravel convention `CraftCms\Cms\Plugin\Concerns\HasTranslations` looks for (`dirname($plugin->getBasePath()).'/lang'`) ahead of the legacy `src/translations` fallback. Message file structure and content are unchanged.
- Updated `crowdin.yml`'s `base_path` from `/src/translations` to `/lang` to match.

### Testing

- Renamed `tests/` to `tests-yii2/`, mirroring the `src`/`src-yii2` split, so `tests/` is reserved for Pest tests covering the new `src/` codebase.
- Removed the Codeception test runner and harness (`codeception.yml`, suite configs, `_bootstrap.php`, `_craft/`, `_data/`, `_envs/`, `_output/`, `_support/`, env files, and the `codeception/*` Composer dependencies) — it's no longer run. The legacy `unit/`, `gql/`, and `fixtures/` test classes remain under `tests-yii2/` as reference material to port to Pest; the empty `acceptance/` and `functional/` suites were removed outright.
- Added a Pest/Orchestra Testbench harness under `tests/` (`TestCase`, `UnitTestCase`, `Pest.php`, `Feature/`, `Unit/`, `Arch/`) for testing `CraftCms\Commerce\` code in `src/`. Run via `composer run tests`.
- Added `craftcms/yii2-adapter` as a `require-dev` dependency and the standard Testbench `package:discover`/`package:purge-skeleton` Composer scripts — both needed for the plugin to install correctly under the standalone Testbench harness, since neither was ever required before (previously only exercised as part of the full `craft6a` project, which already provides them).
- Added `database/migrations/Install.php` (`CraftCms\Commerce\Database\Migrations\Install`), a Laravel-style port of `src-yii2/migrations/Install.php`, so Commerce can be installed via `CraftCms\Cms\Plugin\Concerns\Installable::install()` (which runs a plugin's install migration through Laravel's `Migrator`, incompatible with the legacy Yii2 migration) — this is the first time Commerce has been installed through that code path, since production sites installed under the old Yii2 installer flow before this migration began. `getMigrationsPath()`'s "conventional path" check (`database/migrations/`) picks it up automatically ahead of the legacy path; no other wiring needed. `down()` is a one-line "cannot be reverted" warning, matching Craft core's own ported install migration.
- Fixed `CraftCms\Commerce\Helpers\Locale::switchAppLanguage()` — it only mutated the legacy Yii2 `Craft::$app` locale state, which `CraftCms\Cms\Translation\I18N::getFormattingLocale()` (used by `Currency::formatAsCurrency()` and friends) doesn't read outside of CP requests; it now also calls `app()->setLocale()` so switching language actually affects number/currency formatting.
- Fixed `CraftCms\Commerce\Store\Stores.php`'s schema-version guard (added for pre-5.0.72 upgrade compatibility) — right after a fresh plugin install, `Plugins::getStoredPluginInfo()` only has `['id', 'enabled']` cached (no `schemaVersion` yet), which threw an "undefined array key" error instead of the intended "assume old schema" fallback. A missing key now correctly means "just installed, definitely has the settings columns," not "pre-5.0.72."
- Fixed `tests/TestCase.php` — `CraftCms\Cms\Plugin\Plugins::loadPlugins()`'s internal singleton guard was set before Commerce had a row in the `plugins` table, permanently preventing it from ever registering `craft\commerce\Plugin` as a Laravel service provider, so `Plugin::register()`/`boot()` (GQL argument handlers, widgets, permissions, CP nav, console commands, event listeners, `Macroable` macros, etc.) silently never ran under `composer run tests`. Forgetting the `Plugins` singleton and reloading it right after install fixes this.
