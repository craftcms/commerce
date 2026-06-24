# Release Notes for Craft Commerce 6 WIP

### Removed (deprecated in 5.x)

- `Settings::VIEW_URI_CUSTOMERS`, `VIEW_URI_PROMOTIONS`, `VIEW_URI_SHIPPING`, `VIEW_URI_TAX` constants — deprecated in 5.0.0.
- `Store::setCountries()`, `getCountries()`, `getCountriesList()`, `getAdministrativeAreasListByCountryCode()`, `getMarketAddressCondition()` — deprecated in 5.0.0; use the equivalents on `Store::getSettings()` (i.e. `StoreSettings`).
- `Discount::setExcludeOnSale()` / `getExcludeOnSale()` and the `excludeOnSale` shim — deprecated in 5.0.0; use `Discount::$excludeOnPromotion`.

### Laravel Migration — Stage 5m: Discount

Migrated `craft\commerce\models\Discount` → `CraftCms\Commerce\Promotion\Models\Discount`.

Key changes:
- `craft\base\Model` (Yii2) → `CraftCms\Cms\Component\Component` (Laravel)
- Yii2 `new Query()->select()->from()->leftJoin()->where()->column()` → `DB::table()->leftJoin()->where()->pluck()->all()` for the purchasable/category relations loaders
- `Craft::$app->getConditions()->createCondition()` → `Conditions::createCondition()`
- `Craft::$app->getFormatter()->asPercent()` → `I18N::getFormatter()->asPercent()`
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()`
- Yii2 `defineRules()` → Laravel `getRules()`; closure validators rewritten with `$fail()` pattern; `Rule::in()` for `categoryRelationshipType` and `appliedTo`
- `CouponsValidator` retained at the legacy path (covered by closure rule later)
- `craft\elements\conditions\ElementConditionInterface` → `CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface`
- `craft\commerce\elements\conditions\*` (DiscountOrderCondition, DiscountCustomerCondition, DiscountAddressCondition) retained as old namespace references
- `Order` element and `DiscountRecord` retained as old namespace references

### Laravel Migration — Stage 5k: Store

Migrated `craft\commerce\models\Store` → `CraftCms\Commerce\Store\Models\Store`.

Key changes:
- `craft\base\Model` (Yii2) → `CraftCms\Cms\Component\Component` (Laravel)
- `craft\helpers\App::parseEnv()` → `CraftCms\Cms\Support\Env::parse()`
- `craft\helpers\App::parseBooleanEnv()` → `CraftCms\Cms\Support\Env::parseBoolean()`
- `craft\helpers\UrlHelper::cpUrl()` → `CraftCms\Cms\Support\Url::cpUrl()`
- `craft\models\Site` → `CraftCms\Cms\Site\Data\Site`
- `UniqueValidator` → `Illuminate\Validation\Rule::unique()` scoped by id
- Yii2 closure validator for "currency cannot change after orders exist" → Laravel closure rule with `$fail()`
- `Craft::$app->getDeprecator()->log()` → `CraftCms\Cms\Support\Facades\Deprecator::log()`
- `Craft::t('commerce', ...)` → global `t(..., category: 'commerce')`
- Yii2 `attributes()` override (added `name`/`settings`) → `fields()` override under the new serialization layer
- Dropped `EnvAttributeParserBehavior` — the existing `getXxx(bool $parse)` pattern already handles env parsing on every accessor
- `ZoneAddressCondition`, `Order`, and `\craft\commerce\records\Store` retained as old namespace references

### Laravel Migration — Stage 5j: Settings stub + DummyPlan

`craft\commerce\models\Settings` already lived at `CraftCms\Commerce\Settings` from Stage 5a, but the legacy `src-yii2/models/Settings.php` still held the full Yii2 implementation. Now:
- `src-yii2/models/Settings.php` replaced with a `class_alias` stub.
- `src/Settings.php` gained the `setAttributes()` override that strips deprecated Commerce-4 settings keys, preserving backward compatibility for project configs that still reference `orderPdfFilenameFormat`, `autoSetNewCartAddresses`, etc.

Migrated `craft\commerce\models\subscriptions\DummyPlan` → `CraftCms\Commerce\Subscription\Models\DummyPlan`. Still extends the unmigrated `craft\commerce\base\Plan`; switched to the new `CraftCms\Commerce\Subscription\Contracts\PlanInterface` argument type.

### Bug Fixes

- Fixed `Table` constants in `src/Database/Table.php` using Yii2 `{{%tablename}}` prefix syntax instead of plain table names, causing "Base table or view not found" MySQL errors when Laravel's query builder passed the literal string to the database.
- Fixed infinite recursion in `ShippingRule::getOptions()` caused by calling `$this->toArray()`, which internally calls `getObjectVars()`, triggering PHP 8.4 property hook getters (`$config`, `$errors`) on nested condition objects and looping back into serialization. Replaced with an explicit scalar property array.
- Fixed infinite recursion in `ShippingMethodOrderCondition`, `ShippingRuleOrderCondition`, and `DiscountOrderCondition` `config()` methods where `$this->toArray(['storeId'])` was calling `getObjectVars()`, triggering the `$config` property hook getter, which called `getConfig()` → `config()` → `toArray()` again. Replaced with `['storeId' => $this->storeId]`.

### Laravel Migration — Stage 5i: CatalogPricingRule

Migrated `craft\commerce\models\CatalogPricingRule` → `CraftCms\Commerce\Catalog\Models\CatalogPricingRule`.

Key changes:
- `craft\base\Model` (Yii2) → `CraftCms\Cms\Component\Component` (Laravel)
- `Craft::$app->getFormatter()->asPercent()` → `I18N::getFormatter()->asPercent()`
- `Craft::$app->getConditions()->createCondition()` → `Conditions::createCondition()`
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()`
- Yii2 `defineRules()` → Laravel `getRules()` with `Rule::in([...])` for the `apply` field
- `craft\elements\conditions\*` condition classes retained as old namespace references (not yet migrated)
- `craft\commerce\elements\Product`, `Variant`, `base\Purchasable`, `records\CatalogPricingRule` retained as old namespace references
- `Plugin::getInstance()->getCurrencies()->getTeller()` retained until `Currencies` service migrated to `src/`

### Laravel Migration — Stage 5h: ShippingRule

Migrated `craft\commerce\models\ShippingRule` → `CraftCms\Commerce\Shipping\Models\ShippingRule`.

Key changes:
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()`
- `Craft::$app->getConditions()->createCondition()` → `Conditions::createCondition()`
- Yii2 attribute-based closure validators (`function($attribute)` + `$this->addError()`) → Laravel closures (`function($attribute, $value, \Closure $fail)` + `$fail()`)
- `validateShippingRuleCategories` method validator → inline closure in `getRules()`; `$this->addModelErrors()` available on new Component via `Validates` trait
- `$this->getAttributes()` in `getOptions()` → `$this->toArray()`
- `craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition` and `customers\ShippingRuleCustomerCondition` retained as old namespace references (not yet migrated)
- `craft\commerce\elements\Order`, `craft\commerce\records\ShippingRuleCategory` retained as old namespace references

### Laravel Migration — Stage 5g: ShippingMethod, BaseShippingMethod, and ShippingMethodOption

Migrated the shipping method model hierarchy from `craft\commerce\` to `src/` in the `CraftCms\Commerce\` namespace.

New class locations:
- `craft\commerce\base\ShippingMethod` (abstract) → `CraftCms\Commerce\Shipping\Models\BaseShippingMethod`
- `craft\commerce\models\ShippingMethod` → `CraftCms\Commerce\Shipping\Models\ShippingMethod`
- `craft\commerce\models\ShippingMethodOption` → `CraftCms\Commerce\Shipping\Models\ShippingMethodOption`

Key changes:
- `craft\base\Chippable/Colorable/Iconic/Statusable` → `CraftCms\Cms\Component\Contracts\Chippable/Colorable/Iconic/Statusable`
- `craft\enums\Color` → `CraftCms\Cms\Shared\Enums\Color`
- `craft\commerce\errors\NotImplementedException` → `\BadMethodCallException` (inline)
- `UniqueValidator` → `Rule::unique()` (Laravel validation)
- `AttributeTypecastBehavior` — dropped (Yii2-only)
- `CurrencyAttributeBehavior` / `currencyAttributes()` / `getCurrency()` — dropped from `ShippingMethodOption` (Yii2-only)
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()`
- `Craft::$app->getConditions()->createCondition()` → `Conditions::createCondition()`
- `craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition` and `customers\ShippingMethodCustomerCondition` retained as old namespace references (not yet migrated)
- `craft\commerce\elements\Order` retained as old namespace reference (not yet migrated)

### Laravel Migration — Stage 5f: Interfaces and Sale, StoreSettings, Transaction Models

Migrated three models and four interfaces from `craft\commerce\` to domain-organized classes/interfaces under `src/` in the `CraftCms\Commerce\` namespace.

New interface locations:
- `craft\commerce\base\TaxIdValidatorInterface` → `CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface`
- `craft\commerce\base\TaxEngineInterface` → `CraftCms\Commerce\Tax\Contracts\TaxEngineInterface`
- `craft\commerce\base\ZoneInterface` → `CraftCms\Commerce\Base\ZoneInterface`
- `craft\commerce\base\SubscriptionResponseInterface` → `CraftCms\Commerce\Subscription\Contracts\SubscriptionResponseInterface`

New model locations:
- `craft\commerce\models\Sale` → `CraftCms\Commerce\Promotion\Models\Sale`
- `craft\commerce\models\StoreSettings` → `CraftCms\Commerce\Store\Models\StoreSettings`
- `craft\commerce\models\Transaction` → `CraftCms\Commerce\Payment\Models\Transaction`

Key changes:
- `craft\base\ComponentInterface` → `CraftCms\Cms\Component\Contracts\ComponentInterface` (in `TaxEngineInterface`)
- `new Query()->select()->from()->leftJoin()->where()->column()` → `DB::table()->leftJoin()->where()->pluck()->all()` (in `Sale`)
- `Craft::$app->getFormatter()->asPercent()` → `I18N::getFormatter()->asPercent()` (in `Sale`)
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()` (in `StoreSettings`)
- `craft\helpers\ArrayHelper::firstValue()` → `Arr::first()` (in `StoreSettings`)
- `Address::findOne($id)` → `Elements::getElementById($id, Address::class)` (in `StoreSettings`)
- `Craft::$app->getElements()->saveElement()` → `Elements::saveElement()` (in `StoreSettings`)
- `Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language)` → `Addresses::getCountryRepository()->getList(app()->getLocale())` (in `StoreSettings`)
- `Craft::$app->getConditions()->createCondition()` → `Conditions::createCondition()` (in `StoreSettings`)
- `CurrencyAttributeBehavior` behavior — dropped (Yii2-only)
- Hash generation moved from `__construct` override to a direct call in the new `__construct` (in `Transaction`)
- `init()` currency defaults moved into `__construct` in `Transaction`
- `craft\commerce\elements\Order` and `craft\commerce\base\Gateway` retained as old namespace references (not yet migrated)

### Laravel Migration — Stage 5e: OrderStatus, PaymentSource, InventoryLocation, and CatalogPricing

Migrated four additional models from `craft\commerce\models\` to domain-organized classes under `src/` in the `CraftCms\Commerce\` namespace.

New model locations:
- `craft\commerce\models\OrderStatus` → `CraftCms\Commerce\Order\Models\OrderStatus`
- `craft\commerce\models\PaymentSource` → `CraftCms\Commerce\Payment\Models\PaymentSource`
- `craft\commerce\models\InventoryLocation` → `CraftCms\Commerce\Inventory\Models\InventoryLocation`
- `craft\commerce\models\CatalogPricing` → `CraftCms\Commerce\Catalog\Models\CatalogPricing`

Key changes:
- `Yii2 SoftDeleteTrait` — not used in new code; `dateDeleted` property kept inline on `OrderStatus`
- `Cp::statusLabelHtml()` → `app(CraftCms\Cms\Cp\Html\StatusHtml::class)->statusLabelHtml()`
- `Html::encode()` → `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE)`
- `Db::uidsByIds(Table::EMAILS, $ids)` → `DB::table(Table::EMAILS)->uidsByIds($ids)` (Laravel query builder macro)
- `craft\elements\Address` → `CraftCms\Cms\Address\Elements\Address` (new class has `countryCode`, `addressLine1`, `getCountryCode()`, `title` properties natively)
- `craft\base\Actionable/Chippable/CpEditable` → `CraftCms\Cms\Component\Contracts\Actionable/Chippable/CpEditable`
- `Craft::$app->getUser()->getIdentity()?->can()` → `request()->craftUser()?->can()` (new `CraftUser` auth pattern)
- `HandleValidator` → inline `regex:/^[a-zA-Z][a-zA-Z0-9_]*$/` with reserved-word closure rule
- `CurrencyAttributeBehavior` — dropped entirely (Yii2 behavior, not used in new system)
- `Craft::$app->getDeprecator()->log()` → `CraftCms\Cms\Support\Facades\Deprecator::log()`
- Updated `InventoryLocationTrait`, `InventoryMovementInterface`, `InventoryMovement`, `DeactivateInventoryLocation`, `InventoryLevel`, `InventoryFulfillmentLevel`, `InventoryTransaction` to reference the new `CraftCms\Commerce\Inventory\Models\InventoryLocation` class

### Laravel Migration — Stage 5d: Email, PDF, OrderAdjustment, TaxRate, Zones, and Inventory Movements

Migrated email, PDF, order adjustment, tax rate, zone base classes, and all inventory movement models from `craft\commerce\` to domain-organized classes under `src/` in the `CraftCms\Commerce\` namespace.

New model locations:
- `craft\commerce\models\Email` → `CraftCms\Commerce\Email\Models\Email`
- `craft\commerce\models\Pdf` → `CraftCms\Commerce\Pdf\Models\Pdf`
- `craft\commerce\models\OrderAdjustment` → `CraftCms\Commerce\Order\Models\OrderAdjustment`
- `craft\commerce\models\TaxRate` → `CraftCms\Commerce\Tax\Models\TaxRate`
- `craft\commerce\models\ShippingAddressZone` → `CraftCms\Commerce\Shipping\Models\ShippingAddressZone`
- `craft\commerce\models\TaxAddressZone` → `CraftCms\Commerce\Tax\Models\TaxAddressZone`
- `craft\commerce\base\Zone` (abstract) → `CraftCms\Commerce\Base\Zone`
- `craft\commerce\base\InventoryMovement` (abstract) → `CraftCms\Commerce\Inventory\Models\InventoryMovement`
- `craft\commerce\models\inventory\InventoryManualMovement` → `CraftCms\Commerce\Inventory\Models\InventoryManualMovement`
- `craft\commerce\models\inventory\InventoryCommittedMovement` → `CraftCms\Commerce\Inventory\Models\InventoryCommittedMovement`
- `craft\commerce\models\inventory\InventoryFulfillMovement` → `CraftCms\Commerce\Inventory\Models\InventoryFulfillMovement`
- `craft\commerce\models\inventory\InventoryRestockMovement` → `CraftCms\Commerce\Inventory\Models\InventoryRestockMovement`
- `craft\commerce\models\inventory\InventoryTransferMovement` → `CraftCms\Commerce\Inventory\Models\InventoryTransferMovement`
- `craft\commerce\models\inventory\InventoryLocationDeactivatedMovement` → `CraftCms\Commerce\Inventory\Models\InventoryLocationDeactivatedMovement`
- `craft\commerce\models\inventory\DeactivateInventoryLocation` → `CraftCms\Commerce\Inventory\Models\DeactivateInventoryLocation`

New shared infrastructure:
- `CraftCms\Commerce\Store\Concerns\StoreTrait` — shared `storeId`/`getStore()` for all store-aware models
- `CraftCms\Commerce\Store\Contracts\HasStoreInterface` — interface for store-aware models (already existed)

Key changes:
- `craft\helpers\Json::decode()` → `CraftCms\Cms\Support\Json::decode()`
- `craft\helpers\Json::decodeIfJson()` → `CraftCms\Cms\Support\Json::decodeIfJson()`
- `craft\elements\Address` → `CraftCms\Cms\Address\Elements\Address`
- `App::parseEnv()` → `CraftCms\Cms\Support\Env::parse()`
- `App::mailSettings()->fromEmail/fromName` → `CraftCms\Cms\Email\Data\EmailSettings::fromProjectConfig()->fromEmail/fromName`
- `Craft::$app->getSites()->getSiteById()` → `CraftCms\Cms\Support\Facades\Sites::getSiteById()`
- `Craft::$app->getSites()->getPrimarySite()` → `CraftCms\Cms\Support\Facades\Sites::getPrimarySite()`
- `Craft::$app->getConditions()->createCondition()` → `CraftCms\Cms\Support\Facades\Conditions::createCondition()`
- `Craft::$app->getFormatter()->asPercent()` → `CraftCms\Cms\Support\Facades\I18N::getFormatter()->asPercent()`
- `Illuminate\Validation\Rule::unique()` used for handle/name uniqueness scoped by `storeId`
- `new Query()` Yii2 queries → `DB::table()` Laravel fluent builder
- `InventoryMovement::init()` hash preload removed; lazy-initialized in `getInventoryMovementHash()`
- Inventory movement validation closure rules use `$fail('msg')` pattern
- `craft\commerce\base\StoreTrait` (src-yii2) marked `@deprecated`; new `CraftCms\Commerce\Store\Concerns\StoreTrait` used in all migrated models

### Laravel Migration — Stage 5c: Inventory, Catalog, and Transfer Models

Migrated inventory, catalog, and transfer models from `craft\commerce\models\` to domain-organized classes under `src/` in the `CraftCms\Commerce\` namespace.

New model locations:
- `craft\commerce\models\ProductTypeSite` → `CraftCms\Commerce\Catalog\Models\ProductTypeSite`
- `craft\commerce\models\InventoryItem` → `CraftCms\Commerce\Inventory\Models\InventoryItem`
- `craft\commerce\models\InventoryFulfillmentLevel` → `CraftCms\Commerce\Inventory\Models\InventoryFulfillmentLevel`
- `craft\commerce\models\InventoryLevel` → `CraftCms\Commerce\Inventory\Models\InventoryLevel`
- `craft\commerce\models\InventoryTransaction` → `CraftCms\Commerce\Inventory\Models\InventoryTransaction`
- `craft\commerce\models\inventory\UpdateInventoryLevel` → `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevel`
- `craft\commerce\models\inventory\UpdateInventoryLevelInTransfer` → `CraftCms\Commerce\Inventory\Models\UpdateInventoryLevelInTransfer`
- `craft\commerce\models\TransferDetail` → `CraftCms\Commerce\Transfer\Models\TransferDetail`

New trait locations (for use by migrated models):
- `craft\commerce\base\InventoryItemTrait` → `CraftCms\Commerce\Inventory\Concerns\InventoryItemTrait`
- `craft\commerce\base\InventoryLocationTrait` → `CraftCms\Commerce\Inventory\Concerns\InventoryLocationTrait`

Key changes:
- `Craft::$app->getElements()->getElementById()` → `CraftCms\Cms\Support\Facades\Elements::getElementById()`
- `craft\elements\User` return type → `CraftCms\Cms\User\Elements\User`
- `craft\helpers\UrlHelper::cpUrl()` → `CraftCms\Cms\Support\Url::cpUrl()`
- `yii\base\InvalidConfigException` → native `\InvalidArgumentException`
- `unique` Yii2 validator with `targetClass`/`targetAttribute` → `Illuminate\Validation\Rule::unique(table, column)`
- `'in'` Yii2 validator with `range` → `Rule::in([...values])`
- `InventoryTransactionType::allowedManualAdjustmentTypes()` returns enum array; mapped to string values via `array_map(fn($t) => $t->value, ...)`
- `TransferDetail::init()` preloading removed; `getTransfer()` now lazy-loads via `Transfer::find()->id()->one()`
- `InventoryLocationTrait` still references `craft\commerce\models\InventoryLocation` (not yet migrated); old trait in `src-yii2/base/` retained for `InventoryMovement` base class

### Laravel Migration — Stage 5b: Models with Relationships

Migrated models that have relationship getters (lazy-loading related models via services) from `craft\commerce\models\` to domain-organized classes under `src/` in the `CraftCms\Commerce\` namespace.

New model locations:
- `craft\commerce\models\OrderNotice` → `CraftCms\Commerce\Order\Models\OrderNotice`
- `craft\commerce\models\OrderHistory` → `CraftCms\Commerce\Order\Models\OrderHistory`
- `craft\commerce\models\SiteStore` → `CraftCms\Commerce\Store\Models\SiteStore`
- `craft\commerce\models\ShippingRuleCategory` → `CraftCms\Commerce\Shipping\Models\ShippingRuleCategory`
- `craft\commerce\models\payments\BasePaymentForm` → `CraftCms\Commerce\Payment\Forms\BasePaymentForm`
- `craft\commerce\models\payments\OffsitePaymentForm` → `CraftCms\Commerce\Payment\Forms\OffsitePaymentForm`
- `craft\commerce\models\payments\CreditCardPaymentForm` → `CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm`
- `craft\commerce\models\payments\DummyPaymentForm` → `CraftCms\Commerce\Payment\Forms\DummyPaymentForm`

Key changes:
- `Craft::$app->getSites()->getSiteById()` → `CraftCms\Cms\Support\Facades\Sites::getSiteById()`
- `Craft::$app->getUsers()->getUserById()` → `CraftCms\Cms\Support\Facades\Users::getUserById()`
- `craft\helpers\ArrayHelper::firstWhere()` → `collect()->first()`
- `craft\helpers\Db::uidById()` → `DB::table(...)->uidById()` (Craft 6 macro on the query builder)
- `yii\base\NotSupportedException` → native `\LogicException`
- `CreditCardPaymentForm::setAttributes()` now overrides the `Validates` trait's `setAttributes()` for expiry field parsing
- `CreditCardPaymentForm` Luhn check converted from a Yii2 method validator to a Laravel closure rule in `getRules()`

Also fixed:
- `phpstan.neon` scan file paths for `yii2-adapter/legacy/Craft.php` and `yii2-adapter/lib/yii2/Yii.php` (relative paths replacing missing vendor paths)
- Added `vendor/craftcms/cms/src/helpers.php` to PHPStan scan files so the global `t()` function is recognized
- Added `use function CraftCms\Cms\t;` to files that call `t()`

### Laravel Migration — Stage 5a: Simple Value Models

Migrated simple value models (scalar properties, no element ties) from `craft\commerce\models\` to domain-organized classes under `src/` in the `CraftCms\Commerce\` namespace. All new classes extend `CraftCms\Cms\Component\Component` and use `getRules()` with Laravel validation syntax instead of Yii2's `defineRules()`.

New model locations:
- `craft\commerce\models\Coupon` → `CraftCms\Commerce\Promotion\Models\Coupon`
- `craft\commerce\models\TaxCategory` → `CraftCms\Commerce\Tax\Models\TaxCategory`
- `craft\commerce\models\ShippingCategory` → `CraftCms\Commerce\Shipping\Models\ShippingCategory`
- `craft\commerce\models\LineItemStatus` → `CraftCms\Commerce\Order\Models\LineItemStatus`
- `craft\commerce\models\PaymentCurrency` → `CraftCms\Commerce\Payment\Models\PaymentCurrency`
- `craft\commerce\models\PurchasableStore` → `CraftCms\Commerce\Purchasable\Models\PurchasableStore`
- `craft\commerce\models\Settings` → `CraftCms\Commerce\Settings`
- `craft\commerce\models\subscriptions\CancelSubscriptionForm` → `CraftCms\Commerce\Subscription\Forms\CancelSubscriptionForm`
- `craft\commerce\models\subscriptions\SubscriptionForm` → `CraftCms\Commerce\Subscription\Forms\SubscriptionForm`
- `craft\commerce\models\subscriptions\SwitchPlansForm` → `CraftCms\Commerce\Subscription\Forms\SwitchPlansForm`
- `craft\commerce\models\subscriptions\SubscriptionPayment` → `CraftCms\Commerce\Subscription\Models\SubscriptionPayment`
- `craft\commerce\models\responses\Dummy` → `CraftCms\Commerce\Payment\Gateway\Responses\Dummy`
- `craft\commerce\models\responses\Manual` → `CraftCms\Commerce\Payment\Gateway\Responses\Manual`
- `craft\commerce\models\responses\DummySubscriptionResponse` → `CraftCms\Commerce\Subscription\Responses\DummySubscriptionResponse`

Key changes:
- `craft\base\Chippable/Colorable/Iconic` → `CraftCms\Cms\Component\Contracts\Chippable/Colorable/Iconic`
- `craft\enums\Color` → `CraftCms\Cms\Shared\Enums\Color`
- `craft\helpers\UrlHelper::cpUrl()` → `CraftCms\Cms\Support\Url::cpUrl()`
- `Craft::t()` → global `t()`
- `craft\helpers\Cp::requestedSite()` → `app(CraftCms\Cms\Cp\RequestedSite::class)->get()`
- `craft\helpers\Cp::statusLabelHtml()` → `app(CraftCms\Cms\Cp\Html\StatusHtml::class)->statusLabelHtml()`
- `craft\helpers\StringHelper::randomString()` → `CraftCms\Cms\Support\Str::random()`
- `craft\helpers\ConfigHelper::localizedValue()` → `CraftCms\Cms\Support\Config::localizedValue()`
- `yii\base\InvalidConfigException` → native `\InvalidArgumentException`
- `craft\helpers\ArrayHelper::getColumn()` → `array_column()`

### Removed: Yii2 Debug Panel
- Removed the Commerce Yii2 debug panel entirely — it relied on the Yii2 debug module (`craft\debug\Module`), which no longer exists in Craft CMS 6 / Laravel.
- Deleted `src-yii2/debug/CommercePanel.php`.
- Deleted `src-yii2/helpers/DebugPanel.php` and the previously migrated `src/Helpers/DebugPanel.php`.
- Deleted `src-yii2/events/CommerceDebugPanelDataEvent.php` and `src/Cp/Events/CommerceDebugPanelDataEvent.php`.
- Deleted `src-yii2/views/debug/commerce/` (detail, model, summary views).
- Removed `_registerDebugPanels()` method and its `onInit` registration from `src-yii2/Plugin.php`.
- Removed all `DebugPanel::prependOrAppendModelTab()` calls and imports from 19 controllers.

### Laravel Migration — Stage 3: Events (Call-site Updates)
- All `src-yii2/` event instantiation call sites updated from Yii2 array-config syntax (`new XxxEvent(['prop' => $val])`) to PHP 8 named argument syntax (`new XxxEvent(prop: $val)`).
- All new `CraftCms\Commerce\*\Events\` classes now use constructor property promotion for clean, typed initialization.
- Fixed `phpstan.neon` to work with Craft 6's restructured vendor layout (removed dependency on `craftcms/phpstan.neon`, added correct `scanFiles` and `scanDirectories` for legacy code awareness).
- Fixed `CraftCms\Commerce\Subscription\Events\PlanEvent`, `CreateSubscriptionEvent`, and `SubscriptionSwitchPlansEvent` to import `craft\commerce\base\Plan` (not the non-existent `craft\commerce\models\Plan`).

### Laravel Migration — Stage 3: Events
- Moved all 56 event classes from `craft\commerce\events\` (`src-yii2/events/`) to domain-organized `CraftCms\Commerce\*\Events\` classes under `src/`:
  - `CraftCms\Commerce\Catalog\Events\` — product/variant snapshot, product type, purchase variant, purchasables table query events
  - `CraftCms\Commerce\Cp\Events\` — debug panel data event
  - `CraftCms\Commerce\Email\Events\` — email and mail events
  - `CraftCms\Commerce\Inventory\Events\` — inventory movement and level update events
  - `CraftCms\Commerce\Order\Events\` — cart, line item, order status, order notice, purge events
  - `CraftCms\Commerce\Payment\Events\` — payment source, process payment, transaction, refund, webhook events
  - `CraftCms\Commerce\Pdf\Events\` — PDF and PDF render events
  - `CraftCms\Commerce\Promotion\Events\` — discount, sale, and match events
  - `CraftCms\Commerce\Purchasable\Events\` — purchasable availability and shipping events
  - `CraftCms\Commerce\Report\Events\` — report event
  - `CraftCms\Commerce\Shipping\Events\` — register available shipping methods event
  - `CraftCms\Commerce\Store\Events\` — store and delete store events
  - `CraftCms\Commerce\Subscription\Events\` — subscription lifecycle events
  - `CraftCms\Commerce\Tax\Events\` — tax engine and tax ID validator events
- Cancelable events (previously extending `craft\events\CancelableEvent`) now use the `CraftCms\Cms\Shared\Concerns\ValidatableEvent` trait instead.
- Legacy `craft\commerce\events\*` classes replaced with `class_alias` stubs pointing to the new classes.

### Laravel Migration — Stage 2: Interfaces & Base Contracts
- Moved 11 interfaces from `craft\commerce\base\` (`src-yii2/base/`) to domain-organized `CraftCms\Commerce\*\Contracts\` interfaces under `src/`:
  - `CraftCms\Commerce\CatalogPricing\Contracts\CatalogPricingConditionRuleInterface`
  - `CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface`
  - `CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface`
  - `CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface`
  - `CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface`
  - `CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface`
  - `CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface`
  - `CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface`
  - `CraftCms\Commerce\Stats\Contracts\StatInterface`
  - `CraftCms\Commerce\Store\Contracts\HasStoreInterface`
  - `CraftCms\Commerce\Subscription\Contracts\PlanInterface`
- Legacy `craft\commerce\base\*` interface files replaced with `class_alias` stubs.

### Laravel Migration — Stage 1: Constants & Enums
- Moved `craft\commerce\db\Table` to `CraftCms\Commerce\Database\Table`.
- Moved `craft\commerce\enums\InventoryTransactionType` to `CraftCms\Commerce\Inventory\Enums\InventoryTransactionType`.
- Moved `craft\commerce\enums\InventoryUpdateQuantityType` to `CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType`.
- Moved `craft\commerce\enums\LineItemType` to `CraftCms\Commerce\Order\LineItem\Enums\LineItemType`.
- Moved `craft\commerce\enums\TransferStatusType` to `CraftCms\Commerce\Transfer\Enums\TransferStatusType`.
- Legacy enum and constant files replaced with `class_alias` stubs.

### Craft CMS 6 Compatibility
- Updated `craftcms/cms` requirement to `6.0.0-alpha.1`.
- `craft.commerce` is now registered as a macro on `CraftCms\Cms\Twig\Variables\CraftVariable`, so it works with the new Laravel-based Twig variable.
- Updated `craft\commerce\Plugin` to use the `CraftCms\Cms\Support\Facades\Updates` facade.
- Fixed `craft\commerce\elements\Order::getRecalculationMode()` returning `null` before `init()` had run.
- Fixed `craft\commerce\elements\Order::getLink()` return type to `?\Illuminate\Support\HtmlString`.
- Fixed `craft\commerce\elements\Product::setEagerLoadedElements()`, `Subscription::setEagerLoadedElements()`, and `Variant::setEagerLoadedElements()` method signatures to use `\CraftCms\Cms\Element\Data\EagerLoadPlan`.
- Fixed `craft\commerce\elements\Transfer::prepareEditScreen()` method signature to use `\CraftCms\Cms\Http\Responses\CpScreenResponse|\Symfony\Component\HttpFoundation\Response`.
- Fixed `craft\commerce\models\PaymentCurrency::safeAttributes()` return type declaration to match the parent `array` return type.
- Fixed `craft\commerce\base\Purchasable::__unset()` method signature to add `string` type hint and `void` return type.
- Updated `craft\commerce\elements\VariantCollection::make()` to accept variadic arguments.
