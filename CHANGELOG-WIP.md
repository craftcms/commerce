# Release Notes for Craft Commerce 6 WIP

### Laravel Migration — Stage 7a: Purchasable & Donation elements

Migrated `craft\commerce\elements\Donation`, its record, and its query to
`CraftCms\Commerce\Purchasable\Elements\Donation`,
`CraftCms\Commerce\Purchasable\Models\Donation` (Eloquent), and
`CraftCms\Commerce\Purchasable\Queries\DonationQuery`. The abstract
`craft\commerce\base\Purchasable` element also got a new-namespace sibling,
`CraftCms\Commerce\Purchasable\Elements\Purchasable`, along with
`Purchasable\Queries\PurchasableQuery` and `Purchasable\Validation\{PurchasableRules,DonationRules}`.
`Variant` still extends the legacy `craft\commerce\base\Purchasable` (deferred
with `ProductType`/`Product`, see Stage 5l), so the legacy base and its query
are left as full implementations rather than `class_alias` stubs — Donation is
the only current subclass of the new base.

Found and fixed several latent bugs surfaced by having a real, non-abstract
purchasable in the new namespace for the first time:
- `ElementQuery::applySelectParams()` (cms-6) unwraps any `Expression` column
  back into a plain `"column [as alias]"` string and re-wraps it as an
  identifier — fine for simple columns, but it mangles anything more complex
  (e.g. a `CASE WHEN...END` expression) into invalid SQL. `PurchasableQuery`
  avoided this entirely by moving the `salePrice`/`catalogPricingRuleId`
  computations into joined subqueries (plain `DB::table()` builders, never
  touched by that method) instead of raw expressions in `$this->query`'s own
  select list.
- Relatedly, `salePrice` was being selected as an output column at all in both
  the catalog-pricing and plain-pricing branches, which threw "Setting
  read-only property" on hydration — `salePrice` is a getter-only virtual
  attribute (`Purchasable::getSalePrice()`) with no setter. It's no longer
  selected for hydration in either branch, only referenced in `whereParam()`
  filters and joined-subquery `WHERE`/`GROUP BY` clauses, which don't write it
  back to the element.
- `Donation::find()` now returns the new `PurchasableQuery`, not the legacy
  one, so the `instanceof \craft\commerce\elements\db\PurchasableQuery` checks
  in `Purchasables::getPurchasableById()` and `OrdersController` silently
  stopped matching for donations (breaking the `forCustomer()` catalog-pricing
  scope). Both now check for either query class.
- `Services\Inventory` and both its legacy wrappers (`src-yii2/services/{Inventory,Purchasables}.php`)
  type-hinted their `$purchasable` parameters against the legacy
  `craft\commerce\base\Purchasable` class, which threw a `TypeError` the
  moment a `Donation` (or any other new-namespace purchasable) was passed in.
  Widened to `Purchasable|NewPurchasable` (or, where the method already used
  it consistently for its siblings, `PurchasableInterface`). Same fix applied
  to the `getPurchasable()` return types on `InventoryItem`, `InventoryLevel`,
  `InventoryFulfillmentLevel`, and `InventoryTransaction`.
- `PurchasableQuery::forCustomer()`'s customer-id lookup and
  `Purchasable::afterSave()`'s primary-site check still used
  `\Craft::$app->getUser()->getIdentity()`/`\Craft::$app->getSites()` — old
  Craft-core calls, not permitted in `src/`. Replaced with `currentUser()?->getCraftUserId()`
  and `Sites::getPrimarySite()`.

Verified with a real save → refetch → availability/stock checks → delete
cycle against the dev database via `php craft exec:exec` (Codeception is
currently unable to boot at all in this environment — `craft\test\TestSetup`
no longer exists — a pre-existing, unrelated infrastructure gap, not
something this stage introduced or fixed).

### Laravel Migration — Stage 6i: Customer & misc services (Stage 6 complete)

Migrated the last nine services — `Customers`, `Subscriptions`, `Plans`,
`Emails`, `Pdfs`, `Formulas`, `Webhooks`, `Stores`, and `StoreSettings` —
to `src/Services/`, completing Stage 6. Removed the `Store` service
entirely (deprecated since 5.0.0 in favor of `Stores`, zero call sites
beyond its own registration).

`Subscriptions` and `Plans` turned out tractable despite being tied to
the unmigrated `Subscription` element and `Plan` base class: both stay
as legacy type-hints throughout (the same pattern already established for
`Order` in `Orders`/`Payments`), and `Subscriptions`' own field layout
handlers are single-layout (not the dual-`FieldLayoutBehavior` blocker
that deferred `ProductTypes`), so they only needed the same
`craft\models\FieldLayout` passthrough already used in `Orders`.

Found and fixed three more latent bugs while migrating:
- `PlanEvent`, `CreateSubscriptionEvent`, and `SubscriptionSwitchPlansEvent`
  (Stage 3) all imported a non-existent `craft\commerce\models\Plan` —
  there is no `models\Plan`, only `base\Plan`. Fixed all three imports.
- `WebhookEvent::$response` (Stage 3) was typed `Illuminate\Http\Response`,
  but `WebhooksController` and the gateway webhook pipeline it runs on are
  still entirely Yii2, so the only real value that will ever reach it is a
  `yii\web\Response`. Widened to a union of both until that pipeline
  migrates.
- `RefundTransactionEvent`-style: `Carts::purgeIncompleteCarts()`'s
  Yii2 `Query::count()` can return a numeric string depending on the DB
  driver; the original had no return type declared, so the new strict
  `int` return type surfaced this only once added.

`Carts::init()` (Yii2 lifecycle) becomes a constructor. `Carts` is the
checkout-critical path in this stage, so beyond `tinker` checks it was
verified with a real HTTP request through `commerce/cart/get-cart`,
confirming the full new constructor and cart-lookup logic runs correctly
before hitting the same pre-existing `OrderQuery` bug already confirmed
independent of this work in Stage 6g/6h.

`CartPurgeEvent::$inactiveCartsQuery` is typed `craft\db\Query`
specifically so third-party listeners can extend the purge query, so
`purgeIncompleteCarts()` keeps building it the Yii2 way rather than
switching to the Laravel query builder, to honor that contract.

Resolved now-unblocked TODOs across `OrderStatus`, `Sale`, `StoreTrait`,
`CatalogPricing`/`CatalogPricingRule`, `ShippingRule`, `Email`, and
`DeactivateInventoryLocation` that were waiting on `Stores`, `Emails`,
`Pdfs`, `Formulas`, `Purchasables`, or `CatalogPricingRules`.

### Laravel Migration — Stage 6h: Orders & Carts services

Migrated `Orders`, `Carts`, `OrderNotices`, `OrderHistories`,
`OrderAdjustments`, `OrderStatuses`, and `LineItemStatuses` to
`src/Services/`. Deferred `LineItems`: its entire purpose is CRUD on the
still-unmigrated `craft\commerce\models\LineItem` (deferred in Stage 5n),
same blocker class as `ProductTypes`/`Transfers`.

`Carts::init()` (a Yii2 component lifecycle method — session-based
pre-Commerce-4.0 cart migration, cookie-name setup) becomes a
constructor, since the new base is a plain `#[Singleton]` class with no
`init()` hook; both run exactly once per resolution, so the timing is
unchanged. Removed the `#[\Deprecated]`-since-4.0 `Carts::getCartName()`
(superseded by `$cartCookie['name']`, zero call sites).
`CartPurgeEvent::$inactiveCartsQuery` is typed `craft\db\Query` (a Stage 3
event, already migrated) specifically so third-party listeners can extend
the purge query — `Carts::purgeIncompleteCarts()` keeps building that
query the Yii2 way rather than switching to the Laravel query builder, to
honor that contract.

Found and fixed two real bugs while migrating:
- `OrderStatuses::getOrderCountByStatus()`'s join used
  `craft\db\Table::ELEMENTS`, which is the Yii2-prefixed placeholder
  string `{{%elements}}` — meaningless to Laravel's query builder, which
  has no Yii2 prefix-substitution layer. Fixed to
  `CraftCms\Cms\Database\Table::ELEMENTS` (the plain `'elements'`
  equivalent). Found and fixed the identical latent bug in
  `CatalogPricing::generateCatalogPrices()` (Stage 6b) while auditing for
  other occurrences of the same mistake.
- `Carts::purgeIncompleteCarts()` declared a strict `int` return type on
  a method that returns `Query::count()`, which Yii2 can return as a
  numeric string depending on the DB driver — the original had no return
  type declared, so this only surfaced once the migration added one.

Verified live via `php craft tinker` against the real dev DB, plus a
real HTTP request through `commerce/cart/get-cart` for `Carts` given its
checkout-critical scope — it ran the full new constructor and cart
lookup path and reached the same pre-existing `OrderQuery`/`VariantQuery`/
`ProductQuery` element-query bugs already confirmed independent of this
work (Stage 7 territory: these queries reference `commerce_orders`/
`commerce_products`/`commerce_variants` columns in `ORDER BY`/join
clauses without actually joining those tables — reproduces identically
on unmodified `6.x`).

### Laravel Migration — Stage 6g: Catalog services

Migrated `Products`, `Variants`, and `Purchasables` to `src/Services/`.
`Products::getProductById()`'s structure-ID lookup and
`Purchasables::updateStoreStockCache()`'s stock update swap their Yii2
`craft\db\Query`/`createCommand()` for `Illuminate\Support\Facades\DB`,
matching the established pattern. `Products::afterSaveSiteHandler()`'s
`craft\helpers\Queue::push(new craft\queue\jobs\PropagateElements(...))`
becomes `dispatch(new CraftCms\Cms\Element\Jobs\PropagateElements(...))`
— both deprecated in favor of their Laravel-native equivalents — matching
the equivalent call in Craft core's own `Sites::handleChangedSite()`,
including passing `isNewSite: true` for this same "new site with an old
primary site" scenario, which the old Yii2 job had no equivalent flag for.

Deferred `ProductTypes` (1067 lines) to Stage 7: `saveProductType()`
directly persists the same dual `FieldLayoutBehavior` (Product + Variant)
data that already deferred the `ProductType` model itself in Stage 5l,
plus depends on the unmigrated `craft\models\FieldLayout` and
`craft\services\Structures`. Same blocker class as `Transfers` (Stage 6e).

### Laravel Migration — Stage 6f: Payment services

Migrated `Transactions`, `PaymentSources`, `Gateways`, and `Payments` to
`src/Services/`, in that dependency order (Transactions has no
same-stage dependents; Payments depends on all three of the others).
Legacy `src-yii2/services/*.php` stubs now delegate to
`app(CraftCms\Commerce\Services\X::class)` per-method, per the project's
established stub pattern.

`Transactions` and `PaymentSources` swap their Yii2 `craft\db\Query`
query builders for `Illuminate\Support\Facades\DB` (`DB::table(...)`),
matching the pattern already established in `Coupons`/`ShippingMethods`.
`Gateways` keeps `craft\helpers\Db` (aliased `CraftDb` to avoid a
case-insensitive collision with the `DB` facade import) for
`idByUid()`/`uidsByIds()`/`prepareDateForDb()`, since those have no new
namespace equivalent yet, and keeps `Craft::$app->getProjectConfig()`
directly for the same reason — this is the first migrated service to
touch project config, and no bridge/facade exists for it yet.

Removed `Gateways::getGatewayOverrides()` (deprecated since 3.3, unused
by anything else, and dependent only on the legacy
`commerce-gateways.php` config-file override mechanism) along with its
`$_overrides` cache and the override-merging branch in `createGateway()`.
Also removed the `#[\Deprecated]`-since-4.0 `Transactions::deleteTransaction()`
(superseded by `deleteTransactionById()`, zero call sites).

Found and fixed a latent bug while wiring `Payments`: the already-migrated
`TransactionEvent`, `PaymentSourceEvent`, `ProcessPaymentEvent`, and
`RefundTransactionEvent` classes (Stage 3) are plain property bags with
no constructor. Constructing them Yii2-style
(`new TransactionEvent(['transaction' => $x])`) silently discards the
array argument instead of throwing — PHP allows extra constructor
arguments when no `__construct` is defined — so every event fired this
way carried an uninitialized `transaction` property. Fixed the four
call sites across `Transactions`, `PaymentSources`, and `Payments` to
construct-then-assign instead. Also widened
`RefundTransactionEvent::$amount` to `?float` (was non-nullable `float`),
since `Payments::refundTransaction()`'s `null` (= full refund) is a
normal, common input on the only real call path this event has ever had.

Resolved the `Gateways`-dependent TODOs left in
`Payment\Models\Transaction::getGateway()` and
`Payment\Models\PaymentSource::getGateway()` now that the service they
were waiting on exists in `src/`.

### Laravel Migration — Stage 8: Plugin.php + ServiceProvider

`craft\commerce\Plugin` now extends `CraftCms\Commerce\Plugin` (new,
`src/Plugin.php`, extends `CraftCms\Cms\Plugin\Plugin`) instead of the
legacy `craft\base\Plugin` (a `yii\base\Module`). These two plugin
systems are not bridged, so this drops Commerce out of the Yii2
Module/component-locator system entirely — the only real gap was the
component locator backing the 46 `Plugin::getInstance()->getFoo()`
service getters (838 call sites across `src-yii2/`), ported as
`src/Plugin/Concerns/HasServices.php`, a lazy-instantiate-and-cache
trait mirroring the old getter API exactly. `src-yii2/plugin/Services.php`
is deleted; `Variables.php` is unchanged (never depended on Module-ness).

`src-yii2/plugin/Routes.php` (the `Event::on(UrlManager::class,
EVENT_REGISTER_CP_URL_RULES, ...)` registrations) turned out to have a
subtler dependency: the URL rules themselves still register and match
fine, but `yii\base\Module::createController()` resolves a matched
route like `commerce/orders/order-index` by looking up
`Craft::$app->getModule('commerce')` for the controller namespace —
which only ever worked because `craft\commerce\Plugin` was itself a
`Module` (via the old `craft\base\Plugin` ancestry). Once it wasn't,
every legacy-dispatched Commerce route 404ed despite matching
correctly. Fixed with `src-yii2/plugin/LegacyRoutingModule.php`, a
minimal `yii\base\Module` (routing-only, no settings/services)
registered via `Craft::$app->setModule('commerce', ...)` in `boot()`.

Everything else in the old `init()` (event registrations, projectConfig
listeners, GQL, widgets, permissions, etc.) didn't depend on Module-ness
either — it's all static `Event::on()` calls and `$this->getFoo()` getter
calls — so `init()` just became `boot()` with its body otherwise
untouched.

One real behavioral fix: the new base's `getCpNavItem()` returns a
`NavItem` object instead of an array, and `NavItem::$subnav` defaults to
`false` (not `[]`), so the existing `$ret['subnav']['orders'] = [...]`
pattern needed the object converted to an array (`NavItem::toArray()`)
and `subnav` normalized to `[]` first.

`is()`/`$edition`/`editions()` needed no porting — already provided by
the new base's `HasEditions` concern with identical semantics. Only
`Plugin::getInstance()->id` (one call site, `Products.php`) needed
fixing, to `->handle`, since the new base has no `id` property.

### Fix: migrations fatal under Craft 6's Laravel migrate runner

`ddev artisan craft:migrate/all` fataled with "Cannot redeclare class"
on every Commerce migration. Laravel's `Migrator::getMigrationClass()`
derives a class name from the filename assuming the
`YYYY_MM_DD_HHMMSS_description.php` convention; Commerce's Yii2-style
`mYYMMDD_HHMMSS_description.php` files don't fit, so the derivation
produces a bogus name, the "already loaded" guard misses, and the
Migrator does a second bare `require` on a file already `require_once`'d.

Renamed and rewrote the 6 migrations that hadn't been applied anywhere
yet as genuine Laravel migrations (`return new class extends
CraftCms\Cms\Database\Migration`, using `Schema`/`DB` facades) — a
Yii2 `Migration` object can never satisfy Laravel's
`MigrationStarted`/`MigrationEnded` events, which hard-type-hint
`Illuminate\Database\Migrations\Migration`. Deleted the other 134:
`Install.php` already represents the full current schema and they're
already applied everywhere that matters, so per the plugin migration
docs' own guidance ("bring Install up to date, then cull the rest")
they no longer need to exist as files.

Also added `getConnection()`/`withinTransaction` compatibility members
to the yii2-adapter's `craft\db\Migration` (cms-6 repo) — needed
regardless, since `Install.php` (which stays Yii2-style; it's looked up
by hardcoded filename rather than going through the naming-derivation
path) runs through the same Migrator.

### Dependencies

- Updated `dompdf/dompdf` to `^3.1.6` (from `^2.0.2`).

### Bug fix: legacy service stubs must type-hint the new namespace, not the `class_alias` name

Discovered while verifying Stage 6e: PHP's runtime type enforcement does
**not** treat a `class_alias()`-derived name as interchangeable with its
target for parameter/return/property type declarations, even though
`get_class()`/`instanceof`/`new` all work correctly through the alias. A
legacy stub method declared as `function getFoo(): ?Foo` (where `Foo` is
`use craft\commerce\models\Foo;`, an alias) throws a `TypeError` when it
returns an instance produced by the new service (which returns
`CraftCms\Commerce\X\Models\Foo` directly) — confirmed with a minimal
reproduction, and reproduces identically for parameter types.

Fixed by importing the new FQCN directly (under the old short name) in
every affected stub file, so the declared type IS the type actually
returned/accepted:
- `TaxRates`, `Taxes` (`getEngine(): TaxEngineInterface` — needed two
  imports, since the class's own `implements TaxEngineInterface` must stay
  on the legacy interface while `getEngine()`'s return must point at the
  new one)
- `Inventory`, `InventoryLocations`
- `Coupons`, `CatalogPricingRules`, `Discounts` (`Coupon`/`Discount` only —
  `LineItem`/`PurchasableInterface` correctly stay on the legacy classes,
  which aren't migrated), `Sales`

Stage 6a/6c stubs (`ShippingMethods`, `ShippingRules`, `TaxCategories`,
`TaxZones`, etc.) already used the new-FQCN pattern and needed no changes.

**Not fixed, flagged for follow-up** (found while verifying, out of scope
for this fix since they're unrelated to the stub pattern):
- `CraftCms\Commerce\Promotion\Events\DiscountEvent::$discount` is still
  typed `craft\commerce\models\Discount` (a typed *property*, same root
  cause) — throws when `Discounts::saveDiscount()` assigns it. Pre-existing
  since Stage 3/6b.
- `CraftCms\Commerce\Inventory\Models\InventoryManualMovement::toLocationAfterQuantity()`
  (and `fromLocationAfterQuantity()`) declare `: int` but
  `DB::table(...)->value(...)` can return a numeric string from MySQL,
  which fails under `strict_types`. Pre-existing since Stage 5c.
- Given parameter types are affected by the same bug, there's likely a
  broader population of these across `src-yii2/`/`src/` wherever a
  legacy-aliased type hint receives a new-namespace object — this fix only
  covers what's proven broken (service stub returns) and the resulting
  investigation; a full audit is separate follow-up work.
- `craft\commerce\collections\UpdateInventoryLevelCollection::make()` had
  a signature incompatible with the current `Illuminate\Support\Collection::make($items = [], ...$args)`
  (missing the variadic `...$args`), causing a compile-time fatal on any
  use — fixed alongside this, since it blocked verifying `Inventory::executeUpdateInventoryLevels()`.

### Laravel Migration — Stage 6e: Inventory services

`Inventory` and `InventoryLocations` migrated from `craft\commerce\services` to
`CraftCms\Commerce\Services`. Legacy classes become thin Yii2 Component
wrappers delegating via `app()`.

- `craft\commerce\services\Inventory` → `CraftCms\Commerce\Services\Inventory`
- `craft\commerce\services\InventoryLocations` → `CraftCms\Commerce\Services\InventoryLocations`

`Transfers` (the service) is **deferred** — it's tied to the `Transfer`
element and Craft's legacy Field Layout/project-config system
(`ConfigEvent`, `craft\models\FieldLayout`, `TransferManagementField`),
none of which are migrated yet. Same blocker class as `ProductType` in
Stage 5.

Cross-cutting swaps applied:
- All raw SQL (`craft\db\Query`, `[[col]]` quoting, `yii\db\Expression`)
  converted to Laravel's query builder, including a subquery join
  (`leftJoinSub`) and raw `CASE WHEN` pivots (`selectRaw` with bindings)
  in `getInventoryLevelQuery()`.
- `Craft::$app->getDb()->beginTransaction()/commit()/rollBack()` → `DB::beginTransaction()/commit()/rollBack()`
- `Craft::$app->getUser()->getIdentity()?->id` → `request()->craftUser()?->id`
- `Db::prepareDateForDb(new \DateTime())` → `now()->toDateTimeString()`

`getInventoryLevelQuery()`'s `$limit`/`$offset` params must only call
`->limit()`/`->offset()` when non-null — Laravel's query builder (unlike
Yii2's `Query`) emits a literal `OFFSET 0` for a null offset, which MySQL
rejects without an accompanying `LIMIT`.

The two `Inventory` events (`EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL`,
`EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT`) still fire through the legacy
`Plugin::getInstance()->getInventory()` component so existing
`Event::on(Inventory::class, ...)` listeners keep working (TODO: migrate
event firing to Laravel once the event system is bridged). The two
`InventoryLocations` element-authorization event handlers
(`authorizeInventoryLocationAddressView`/`Edit`) are still registered
against the legacy component instance in `Plugin.php` for the same reason.

Also resolved TODOs in `InventoryItemTrait`, `InventoryLocationTrait`,
`InventoryLevel`, `InventoryTransaction`, `InventoryFulfillmentLevel`,
`InventoryLocation`, `DeactivateInventoryLocation`, `TransferDetail`, and
`Store` — they now call the new services directly instead of going
through `Plugin::getInstance()`.

### Laravel Migration — Stage 6d: Tax services

All tax services migrated from `craft\commerce\services` to
`CraftCms\Commerce\Services` under the Craft 6 service pattern.

- `craft\commerce\services\TaxRates` → `CraftCms\Commerce\Services\TaxRates`
- `craft\commerce\services\Taxes` → `CraftCms\Commerce\Services\Taxes`
- `craft\commerce\services\Vat` → `CraftCms\Commerce\Services\Vat`

Legacy `Plugin::getInstance()->getXxx()` access keeps working — each old service
class is now a thin Yii2 Component wrapper delegating every method via `app()`.

Two leaf dependencies of `Taxes` were migrated alongside it as bonus work:
- `craft\commerce\engines\Tax` → `CraftCms\Commerce\Tax\Engines\Tax` (legacy class is now a `class_alias` stub)
- `craft\commerce\taxidvalidators\EuVatIdValidator` → `CraftCms\Commerce\Tax\Models\EuVatIdValidator` (legacy class is now a `class_alias` stub); swapped `Craft::createGuzzleClient()` for the `Http` facade and `Craft::error()` for `Log::error()`
- `CraftCms\Commerce\Tax\Events\TaxEngineEvent::$engine` now type-hints the new `CraftCms\Commerce\Tax\Contracts\TaxEngineInterface` instead of the legacy one

The two `Taxes` events (`EVENT_REGISTER_TAX_ID_VALIDATORS`, `EVENT_REGISTER_TAX_ENGINE`)
still fire through the legacy `Plugin::getInstance()->getTaxes()` component so
existing `Event::on(Taxes::class, ...)` listeners keep working (TODO: migrate
event firing to Laravel once the event system is bridged).

Also resolved TODOs in `CraftCms\Commerce\Tax\Models\TaxRate`, `TaxCategory`,
and `TaxAddressZone` — they now call the new service classes directly instead
of going through `Plugin::getInstance()`.

Removed `craft\commerce\services\Vat::getVatValidator()`, deprecated since
5.3.0 in favor of `Taxes::getEnabledTaxIdValidators()`.

### Laravel Migration — Stage 6c: Shipping services

All shipping services migrated from `craft\commerce\services` to
`CraftCms\Commerce\Services` under the Craft 6 service pattern.

- `craft\commerce\services\ShippingMethods` → `CraftCms\Commerce\Services\ShippingMethods`
- `craft\commerce\services\ShippingRules` → `CraftCms\Commerce\Services\ShippingRules`
- `craft\commerce\services\ShippingRuleCategories` → `CraftCms\Commerce\Services\ShippingRuleCategories`

Legacy `Plugin::getInstance()->getXxx()` access keeps working — each old service
class is now a thin Yii2 Component wrapper delegating every method via `app()`.

Also resolved TODOs in `CraftCms\Commerce\Shipping\Models\ShippingMethod`,
`ShippingRule`, and `ShippingRuleCategory` — they now call the new service
classes directly instead of going through `Plugin::getInstance()`.

### Laravel Migration — Stage 6b: Promotions services

All promotions services migrated from `craft\commerce\services` to
`CraftCms\Commerce\Services` under the Craft 6 service pattern: plain
PHP classes marked with `#[\Illuminate\Container\Attributes\Singleton]`,
accessed via `app(\CraftCms\Commerce\Services\Foo::class)`.

- `craft\commerce\services\Coupons` → `CraftCms\Commerce\Services\Coupons`
- `craft\commerce\services\CatalogPricingRules` → `CraftCms\Commerce\Services\CatalogPricingRules`
- `craft\commerce\services\CatalogPricing` → `CraftCms\Commerce\Services\CatalogPricing`
- `craft\commerce\services\Discounts` → `CraftCms\Commerce\Services\Discounts`
- `craft\commerce\services\Sales` → `CraftCms\Commerce\Services\Sales`

Legacy `Plugin::getInstance()->getXxx()` access keeps working — each
old service class is now a thin Yii2 Component that delegates every
method to the new singleton via `app()`.

Also updated `CraftCms\Commerce\Promotion\Models\Discount` to import
`CraftCms\Commerce\Services\Coupons` instead of `craft\commerce\services\Coupons`.

Cross-cutting swaps applied throughout:
- `craft\db\Query` → `DB::table()` with fluent builder
- `yii\db\Expression` → `DB::raw()`
- `$this->trigger(self::EVENT_X, new Event([...]))` → `event(new EventClass())`
- `Craft::$app->getDb()->beginTransaction()` → `DB::beginTransaction/commit/rollBack`
- `Craft::$app->getCache()->get/set` → `Cache::get/forever`
- `Craft::info(...)` → `Log::info(...)`
- `Craft::t('commerce', ...)` → `t(..., category: 'commerce')`
- `Craft::$app->getDb()->getIsPgsql()` → `DB::connection()->getDriverName() === 'pgsql'`
- `Craft::$app->getFormatter()->asDecimal()` → `number_format()`
- `StringHelper::randomStringWithChars()` → inlined private `randomStringWithChars()` in `Coupons`
- `QueueHelper::push()` → `dispatch()` (queue jobs — TODO pending)
- `craft\db\Query` returning builder → `\Illuminate\Database\Query\Builder`

### Laravel Migration — Stage 6a: Store config services

All store-config services migrated from `craft\commerce\services` to
`CraftCms\Commerce\Services` under the Craft 6 service pattern: plain
PHP classes marked with `#[\Illuminate\Container\Attributes\Singleton]`,
accessed via `app(\CraftCms\Commerce\Services\Foo::class)`.

- `craft\commerce\services\Currencies` → `CraftCms\Commerce\Services\Currencies`
- `craft\commerce\services\PaymentCurrencies` → `CraftCms\Commerce\Services\PaymentCurrencies`
- `craft\commerce\services\TaxCategories` → `CraftCms\Commerce\Services\TaxCategories`
- `craft\commerce\services\ShippingCategories` → `CraftCms\Commerce\Services\ShippingCategories`
- `craft\commerce\services\TaxZones` → `CraftCms\Commerce\Services\TaxZones`
- `craft\commerce\services\ShippingZones` → `CraftCms\Commerce\Services\ShippingZones`

Legacy `Plugin::getInstance()->getXxx()` access keeps working — each
old service class is now a thin Yii2 Component that delegates every
method to the new singleton via `app()`. Once all callers move to
`app()` the legacy wrappers can be deleted.

Cross-cutting swaps applied throughout:
- Yii2 `Query` builder → Laravel `DB::table()`
- `Craft::createObject(['class' => X, 'attributes' => $row])` → `new X((array) $row)`
- `ArrayHelper::firstWhere/firstValue/map/getColumn` → `collect()` equivalents
- `Craft::$app->getDb()->createCommand()->delete()/insert()/update()` → `DB::table()->...`
- `Craft::$app->getQueue()->push(new ResaveElements([...]))` → `dispatch(new \CraftCms\Cms\Element\Jobs\ResaveElements(elementType: ..., criteria: ...))`
- `$db->getSchema()->getTableSchema(X)->getColumn(Y)` → `Schema::hasColumn(X, Y)`
- `yii\base\Exception` / `yii\base\InvalidConfigException` → `\RuntimeException`

### Removed (deprecated in 5.x)

- `Settings::VIEW_URI_CUSTOMERS`, `VIEW_URI_PROMOTIONS`, `VIEW_URI_SHIPPING`, `VIEW_URI_TAX` constants — deprecated in 5.0.0.
- `Store::setCountries()`, `getCountries()`, `getCountriesList()`, `getAdministrativeAreasListByCountryCode()`, `getMarketAddressCondition()` — deprecated in 5.0.0; use the equivalents on `Store::getSettings()` (i.e. `StoreSettings`).
- `Discount::setExcludeOnSale()` / `getExcludeOnSale()` and the `excludeOnSale` shim — deprecated in 5.0.0; use `Discount::$excludeOnPromotion`.
- `PaymentCurrencies::convertCurrency()` — deprecated in 5.0.0; use `convert()` or `convertAmount()`. (Kept on the legacy `craft\commerce\services\PaymentCurrencies` wrapper only, until the two remaining `src-yii2/` callers — `Order` element, `OrdersController` — migrate.)

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
