# Release Notes for Craft Commerce 6 WIP

### Laravel Migration — Stage 9g: Controllers & Routes (Catalog)

Migrated `ProductsController`, `VariantsController` to `src/Http/Controllers/`, and
`ProductTypesController` to `src/Http/Controllers/Settings/`.

- `Element::SCENARIO_ESSENTIALS` → `$product->ruleset->useScenario(ElementRules::
  SCENARIO_ESSENTIALS)` — same replacement pattern as `SCENARIO_LIVE` in Stages 9b/9f, confirmed
  via cms-6's own `StoreEntryController` (the real "create a new draft entry" reference
  implementation) rather than guessed.
- `Craft::$app->getElements()->canSave($product, $user)` → `$product->canSave($user)` — same
  authorization-moved-onto-the-element pattern as `canView()`/`canDelete()` found in Stage 9f.
- `Craft::$app->getDrafts()->saveElementAsDraft(...)` kept as the legacy accessor call (confirmed
  a real, identically-shaped `CraftCms\Cms\Element\Drafts::saveElementAsDraft()` backs it, per
  `StoreEntryController`'s own usage) — no change needed beyond the calls already made through it.
- `craft\helpers\ElementHelper::generateSlug()`/`tempSlug()` → `CraftCms\Cms\Element\
  ElementHelper` (confirmed via explicit `@deprecated` pointer in the legacy shim);
  `craft\helpers\DateTimeHelper::now()` → Laravel's own `now()` global (also explicitly
  deprecated in favor of it); `craft\helpers\DateTimeHelper::pause()`/`resume()`/`toDateTime()`
  → `CraftCms\Cms\Support\DateTimeHelper` (no deprecation pointer, but a real class with matching
  methods); `craft\enums\PropagationMethod` → `CraftCms\Cms\Element\Enums\PropagationMethod`.
- **Asset bundles left as-is**: confirmed via `docs/6.x/extend/assets.md` that a newer
  `LegacyAssetInterface`/`InternalAssetRegistry` system exists, but the docs themselves flag it as
  "deprecated, proactively... a stopgap" on the way to an Inertia-based UI — not worth migrating
  Commerce's asset bundles (`CommerceCpAsset`, `ProductIndexAsset`, `EditSectionAsset`, etc.) to a
  system that's already superseded. Every `registerAssetBundle()` call throughout this whole
  controller migration (Stages 9a-9g) is left on the legacy path, confirmed still working via live
  testing every time.
- **New, more reliable console-testing technique found**: `auth()->login($user)`/`auth()->
  setUser($user)` hang indefinitely under `craft exec:exec` (documented in Stage 9f), but
  `request()->setUserResolver(fn() => $user)` does not — it satisfies `request()->user()`/
  `request()->craftUser()` (and anything built on those, e.g. `ProductTypes::
  getViewableProductTypeIds()`) without hanging. It does **not** satisfy `currentUser()`/
  `currentUserElement()` (the `CraftCms\Cms` global helpers), which resolve through a different
  path (the Auth facade/guard) — so permission gates built on those two helpers specifically are
  still only verifiable as an anonymous/403 case through this harness. Use `setUserResolver()`
  as the default technique for future stages; it's strictly better than not testing at all.
- **Verification**: `route:list` confirmed all new routes. Live `craft exec:exec` testing (using
  the new `setUserResolver()` technique) fully exercised `ProductTypesController::
  productTypeIndex()` and `editProductType()` end-to-end as an authenticated admin with no errors.
  `ProductsController::create()` was verified up to its `currentUserElement()` gate (product
  type lookup, site resolution, and editable-site fallback all confirmed correct) — blocked only
  by the `currentUserElement()` resolution gap just documented, not a defect in the new code.

### Laravel Migration — Stage 9f (final): Controllers & Routes (Orders)

Migrated `OrdersController` (2,227 lines, 29 actions — the largest and highest-risk single
controller in the whole migration, covering order editing, transactions/refunds, and customer/
address management) to `src/Http/Controllers/`. This completes Stage 9f.

- **Found and fixed a real, repeated bug class specific to this migration**: the legacy file has
  no `declare(strict_types=1)`, so PHP silently coerced numeric strings (every raw
  `$request->input()`/`query()` value) into the `int`-typed parameters of half a dozen strictly-
  typed service methods (`getOrderById`, `getTransactionById`, `getUserById`, `getEmailById`,
  `getStoreBySiteId`, `getInventoryLocationById`, `getPurchasableById`, `AdminTable::
  paginationLinks()`). The new file *does* declare `strict_types=1` (per this project's own
  convention for new code), so every one of those call sites needed an explicit `(int)` cast —
  found via live `craft exec:exec` `TypeError`s on `purchasablesTable()`
  (`getStoreBySiteId()`/`paginationLinks()`), then proactively audited and fixed every other
  matching call site in the file rather than waiting to hit each one individually.
- **Found and fixed a real, pre-existing bug in `transactionRefund()`**: `$transaction->
  paymentCurrency` was read before the `if (!$transaction)` null check — reordered so the null
  check runs first, matching what the code was clearly trying to do.
- **`Craft::$app->getElements()->canView($order)`/`canDelete($order)` → `$order->canView($user)`/
  `canDelete($user)`**: authorization moved from the Elements service onto the element itself and
  now takes an explicit `User` in the new element system, rather than implicitly checking the
  current session user. Replaced with `($user = currentUserElement()) && $order->canView($user)`.
- **`Element::setScenario(Element::SCENARIO_LIVE)` → `$order->ruleset->useScenario(ElementRules::
  SCENARIO_LIVE)`** — same replacement already established for `CartController` (this stage) and
  already used by the already-migrated `Order::validateAddress()`.
- **Dropped `activeAttributes()`-based attribute-scoped validation** in `save()`, same reasoning
  and replacement (`validate(null, false)`) as `CartController`'s `_returnCart()`.
- `Craft::$app->getUser()->getIdentity()` → `currentUserElement()`; `Craft::$app->getView()->
  registerJs($js, View::POS_BEGIN)` → `HtmlStack::js($js, Position::BodyBegin)` (confirmed real
  facade replacement, `src/Support/Facades/HtmlStack.php` in cms-6); `$this->asCpModal()` →
  `new CpModalResponse()` (confirmed real, near-identical fluent API); `$this->requireCpRequest()`
  → `RequireCpRequest::class` route middleware (applied to the 4 CP-only actions —
  `reassign(Modal)?`, `removeCustomerData(Modal)?` — alongside their `deleteUsers` permission,
  nested inside the controller-wide `commerce-manageOrders` group).
- `OrdersController` extends the plain Yii2 `Controller` (not `BaseCpController`/
  `BaseAdminController`) — its own `init()` only ever checked `commerce-manageOrders`, never
  `accessPlugin-commerce`. Its action routes in `routes/actions.php` reflect that (no
  `accessPlugin-commerce` check), while its CP page routes in `routes/cp.php` sit inside the
  existing outer `accessPlugin-commerce` group for consistency with every other CP page route —
  harmless in practice since `commerce-manageOrders` can only be granted to a user who already has
  plugin access, per Craft's nested-permission model.
- **Testing limitation found and documented (not a bug in the new code)**: `auth()->login($user)`
  and `auth()->setUser($user)` both hang indefinitely under `craft exec:exec`'s console context in
  this dev environment — meaning the handful of actions that call `enforceManageOrderPermissions()`
  (`editOrder`, `save`, `refresh`, `getShippingMethodOptions`, `deleteOrder`) couldn't be exercised
  end-to-end as an authenticated user through this harness. Verified everything up to that gate
  instead (confirmed each one reaches `enforceManageOrderPermissions()` cleanly with no unrelated
  errors, and confirmed the gate itself correctly 403s for the anonymous/console user) — the
  downstream `validate(null, false)` + `Elements::saveElement()` logic is identical to
  `CartController::returnCart()`'s already-verified full end-to-end success.
- **Verification**: `route:list` confirmed all 58 new routes (29 actions × dual CP/site
  registration, plus 3 CP pages) with correct HTTP verbs and the correct compound permission
  chains. Live `craft exec:exec` testing fully exercised `purchasablesTable()` (including the
  `ModifyPurchasablesTableQueryEvent` dispatch) and `getIndexSourcesBadgeCounts()` end-to-end
  successfully after the `strict_types` fixes above.

### Laravel Migration — Stage 9f (partial): Controllers & Routes (Cart)

Migrated `CartController` (1,022 lines, the largest front-end controller so far) to
`src/Http/Controllers/`. `BaseFrontEndController` stays in `src-yii2/` — `DownloadsController`,
`PaymentSourcesController`, `PaymentsController`, `UsersController` still extend it.

- **Mutex → `Cache::lock()`**: Yii2's `Craft::$app->getMutex()->acquire($name, $timeout)` (blocks
  up to `$timeout` seconds, returns bool) is replaced by `Cache::lock($name, $ttl)->block($timeout)`
  — confirmed real, pervasive pattern (`ElementDraftsController`, `SaveElementController`,
  `ProjectConfig`, `Structures`, etc. in cms-6). Key difference: Laravel's `block()` *throws*
  `LockTimeoutException` on failure instead of returning `false` — both call sites (`updateCart()`'s
  hard failure, `complete()`'s graceful add-error-and-continue) now catch it and reproduce the
  original branch behavior explicitly, rather than relying on a bool return that no longer happens.
- **Yii2 `RateLimiter` behavior → named Laravel rate limiters + `throttle:` middleware**: added
  `src/Http/RateLimiters/{CartRateLimiter,CartChallengeRateLimiter}.php` (mirroring cms-6's own
  `LoginRateLimiter` pattern exactly — a plain class with a `limit(Request): Limit` method),
  registered via `RateLimiter::for()` in `Plugin::register()` (per Stage 9a's finding that
  `boot()` never fires for a plugin). `CartRateLimiter` returns `Limit::none()` when neither
  `number` nor `couponCode` is present, matching the legacy behavior's conditional `user` callback
  that only rate-limited requests carrying those params. Applied via `->middleware('throttle:...')`
  on the route group in `routes/actions.php`, replacing the controller-level `behaviors()` override.
- **`Element::setScenario(Element::SCENARIO_LIVE)` → `$ruleset->useScenario(ElementRules::
  SCENARIO_LIVE)`**: confirmed via existing precedent in the already-migrated `Order::
  validateAddress()` (`src/Order/Elements/Order.php`).
- **Simplified `_returnCart()`'s attribute-scoped validation**: legacy built an explicit attribute
  list via `activeAttributes()` (a Yii2 Model concept with no equivalent under the new Ruleset
  validation system) merged with custom-field attributes gated behind a `Composer\Semver` Craft-
  version check the code's own comment marked `@TODO Remove ... once Craft >= 4.4 is the minimum
  requirement` — now permanently true for Commerce 6. Replaced with a plain `$this->cart->
  validate(null, false)`, which validates the full ruleset (confirmed via direct research into
  `CraftCms\RulesetValidation\Ruleset`/`Validates` trait source — passing `null` skips the
  `->only()` attribute filter entirely rather than requiring an explicit "everything" list).
  Verified end-to-end via a real `updateCart()` round-trip against an actual cart in the dev DB
  that reached this exact line and returned a real "Cart updated." success response.
- `Craft::$app->getElements()->saveElement($cart, $runValidation, $propagate, $updateSearchIndex)`
  → `Elements::saveElement(...)` facade call — confirmed identical first-4-param order/meaning via
  direct signature comparison, new optional trailing params left at their defaults.
- Dropped the Yii2 `$isConsoleRequest`/cookie-based mutex-key fallback branches specific to
  console-dispatched web requests — this controller is reachable only via real HTTP routing now
  (no `Craft::$app->runAction()`-style console dispatch path exists for it anymore), so those
  branches were dead weight, not a behavior change for any real caller.
- `getBodyParam()` call sites were **not** ported to `Request::post()` — despite the naming
  symmetry, Laravel's `post()` only reads the raw POST-body `ParameterBag` and does **not**
  transparently parse a JSON request body the way Yii2's `getBodyParam()` did. Used `Request::
  input()` (merged query+body, JSON-aware) everywhere instead, accepting the minor superset of
  also honoring query-string params on POST-only routes — a low-risk tradeoff against silently
  breaking JSON-driven cart AJAX calls, which was the real, load-bearing risk.
- `cartArray()`'s `EVENT_MODIFY_CART_INFO` extension point ports to a plain `event(new
  ModifyCartInfoEvent(...))` dispatch — the event class itself (`src/Order/Events/
  ModifyCartInfoEvent.php`) was already migrated in an earlier stage as a plain data class; this
  is the first time anything actually dispatches it.
- **Verification**: `route:list` confirmed all 8 routes (dual CP/site-registered) with the correct
  HTTP verbs and `throttle:` middleware. Direct `craft exec:exec` testing included a full
  `updateCart()` round-trip against a real cart row (mutex acquired via a real `Cache::lock`,
  validate+save succeeded, `asModelSuccess()` returned the expected JSON), plus `forgetCart()`,
  `cartSent()`, and `emailChallenge()`/404-not-found-cart checks. Remaining failures were the
  established console-context limitations inside still-legacy internals the controller calls into
  (`craft\console\Request::getUserIP()`/`getCookies()` inside `src-yii2/services/Carts.php`) — not
  regressions in the new controller.

### Laravel Migration — Stage 9f (partial): Controllers & Routes (Order/Line Item Statuses, User Orders)

Migrated `OrderStatusesController`, `LineItemStatusesController` to
`src/Http/Controllers/Settings/`, and `UserOrdersController` to
`src/Http/Controllers/`. `BaseAdminController`-style (`RequireAdmin` route middleware) for the
first two; `UserOrdersController` is a single anonymous JSON action (matches
`BaseFrontEndController`'s `allowAnonymous = true`), registered in `routes/actions.php` with no
`auth` middleware. `BaseFrontEndController` itself is **not** deleted — `CartController`,
`DownloadsController`, `PaymentSourcesController`, `PaymentsController`, `UsersController` still
extend it (remaining Stage 9f/9h/9k work).

- Both `index()` templates extend `commerce/_layouts/settings` directly (own crumbs/title) →
  `pageTemplate()`; both `_edit` templates are bare content fragments → `CpScreenResponse`. Same
  per-template-shape check as every prior sub-stage, this time both patterns appear within the
  same controller.
- **Found and fixed a real bug**: `OrderStatusesController::edit()`'s email dropdown (`Plugin::
  getInstance()->getEmails()->getAllEmails()`) now returns `CraftCms\Commerce\Email\Models\Email`
  (already migrated to `src/`), not the legacy `craft\commerce\models\Email` the new controller
  was initially typed against — a `TypeError` on the `mapWithKeys()` closure's parameter type.
  Fixed by importing the new `Email` model instead.
- `getOrderStatuses()`/`reorder()`/`delete()` on `OrderStatusesController` and `reorder()`/
  `archive()` on `LineItemStatusesController` all sit under `BaseAdminController::init()`'s
  unconditional `requireAdmin(false)` in the legacy source (not just the index/edit screens) —
  replicated by putting every one of these action routes in the existing shared `RequireAdmin`
  group in `routes/actions.php`, alongside Gateways/Settings/Stores/OrderSettings.
- **Verification**: `route:list` confirmed all new page and action routes (including the
  dual CP/site registration for `user-orders/get-orders`). Direct `craft exec:exec` invocation
  of `index()`/`edit()`/`save()`/`reorder()`/`delete()`/`archive()`/`getOrderStatuses()` — a real
  `save()` round-trip actually created and then deleted a test order status to confirm the model
  save path works end-to-end, not just that it returns a response object. Remaining failures were
  the known console-context limitations (`currentUser` null, missing `Accept` header on the
  synthetic request) — not regressions.

### Laravel Migration — Stage 9e: Controllers & Routes (Store & Settings)

Migrated `StoreManagementController`, `StoresController`, `SettingsController`,
`OrderSettingsController`, `PaymentCurrenciesController` to `src/Http/Controllers/Settings/`.
`BaseStoreManagementController` deleted — nothing extends it anymore.

- **Found and fixed a real, already-merged authorization gap spanning Stages 9b–9d**:
  `BaseStoreManagementController::init()` unconditionally required `commerce-manageStoreSettings`
  for *every* subclass, on top of each area's own specific permission
  (`commerce-manageShipping`/`commerce-manageTaxes`/`commerce-managePromotions`). The route
  middleware added in 9b/9c/9d only checked the specific permission, missing this base check
  entirely. Fixed by wrapping the whole `commerce/store-management/*` route group (in both
  `routes/cp.php` and `routes/actions.php`) in a `can:commerce-manageStoreSettings` middleware
  layer, with each area's specific permission nested inside it — restores the exact compound
  check the legacy `init()` enforced. `PaymentCurrenciesController` and `StoreManagementController`
  only ever needed the base permission (no area-specific one), consistent with the legacy source.
  `PromotionsController`'s bare redirect route is unaffected (it extends `BaseCpController`, not
  `BaseStoreManagementController`).
- **Found and fixed a real, pre-existing bug in `src-yii2/services/Transfers.php`**:
  `getFieldLayout()` was still typed against the legacy `craft\models\FieldLayout`/
  `craft\models\FieldLayoutTab`, but `Craft::$app->getFields()->getLayoutByType()` now returns the
  new `CraftCms\Cms\FieldLayout\FieldLayout` — a `TypeError` on every call. Nothing exercised this
  method until `SettingsController::editTransferSettings()` wired it into a route. Fixed by
  switching the imports/type hints to the new `FieldLayout`/`FieldLayoutTab` classes, which expose
  the same `getTabs()`/`setTabs()`/`isFieldIncluded()`/`setLayout()`/`setElements()` API — confirmed
  via the identical tab-injection pattern already in `src/Catalog/ProductType/Data/ProductType.php`.
- `SettingsController::actionSites()` deliberately dropped — its template
  (`commerce/settings/sites/_edit.twig`) doesn't exist on disk; the real `commerce/settings/sites`
  URL has always pointed at `StoresController::editSiteStores()` instead. Pre-existing dead code,
  not a migration regression.
- **Verification**: `route:list` confirmed every new route (index/edit/save/delete pages plus
  action endpoints) and the corrected middleware chains (`commerce-manageStoreSettings` alone
  for `StoreManagementController`/`PaymentCurrenciesController`; compounded with the area-specific
  permission for Shipping/Tax/Promotions). Direct `craft exec:exec` invocation of every new
  controller method surfaced only known console-context limitations (`currentUser` null,
  `craft\console\Request::getSegments()` missing) — no new regressions once the two real bugs
  above were fixed.

### Laravel Migration — Stage 9d: Controllers & Routes (Promotions)

Migrated `SalesController`, `DiscountsController`, `CatalogPricingRulesController`,
`CatalogPricingController`, `PromotionsController` to `src/Http/Controllers/Settings/`.

- **Found and fixed a real correctness gap from Stage 9b**: `pageTemplate()`-based controllers
  (as opposed to `CpScreenResponse`-based ones) need `storeSwitcher`/`storeSettingsNav` passed
  explicitly — the legacy `BaseStoreManagementController::renderTemplate()` override injected
  these into *every* template automatically, a behavior `HasStoreManagementScreen` doesn't
  replicate (by design — it only wraps the `CpScreenResponse` path). Retroactively fixed
  `ShippingRulesController::edit()` (missed in 9b) to pass `storeSwitcher` explicitly, and did
  the same for `SalesController::index()`/`::edit()` (both `pageTemplate()`-based) here.
- `CatalogPricingController` doesn't use `HasStoreManagementScreen` at all — its `index()`
  template extends `commerce/_layouts/cp` directly (not `store-management`), and it isn't
  store-scoped at the URL level (site is resolved from a query param instead).
- `PromotionsController` is a single-line redirect (`commerce/promotions` → `commerce/promotions/
  sales`) — implemented as a route closure rather than a controller class. Note: this redirect
  target doesn't correspond to any registered route (sales now lives at
  `commerce/store-management/{storeHandle}/sales`) — this was **already true in the original**
  (the redirect predates the store-management URL structure, and the CP nav already bypasses
  this controller entirely, linking straight to `commerce/store-management/{store}/discounts`).
  Preserved as-is rather than "fixed", since changing 404-vs-not behavior here is out of scope.
- `BaseStoreManagementController` is **not** deleted yet — `PaymentCurrenciesController` and
  `StoreManagementController` (Stage 9e) still extend it directly.
- **Verification**: `route:list` confirmed all ~58 new routes/middleware. Direct `craft
  exec:exec` invocation of `edit()` on all three permission-gated controllers correctly
  triggered a 403 (confirmed `currentUserElement()` is legitimately `null` in a console context
  — this demonstrates the authorization gate working, not a bug). `CatalogPricingController::
  index()` and `canUseSales()`/`canUseCatalogPricingRules()` guards verified independently.

### Laravel Migration — Stage 9c: Controllers & Routes (Tax)

Migrated `TaxZonesController`, `TaxCategoriesController`, `TaxRatesController` to
`src/Http/Controllers/Settings/`, reusing Stage 9b's `HasStoreManagementScreen` trait
(`can:commerce-manageTaxes` in place of `commerce-manageShipping`). `BaseTaxSettingsController`
and the 3 legacy controllers deleted outright. All 3 templates are bare content fragments (no
`{% extends %}`), so all 3 use `CpScreenResponse` (no `pageTemplate()` needed this time, unlike
9b's `ShippingRulesController`).

**Found and fixed another instance of the same latent bug from 9b**: `Tax\Models\TaxCategory::
getUiLabel()` also called the global `t()` helper without importing it (`ShippingMethod`/
`ShippingAddressZone`/`TaxRate`/`TaxAddressZone` models all correctly import it — only
`ShippingCategory` and now `TaxCategory` were missing it). Proactively grepped every
`getUiLabel()` implementation under `src/` for the same missing-import pattern afterward — no
further instances found.

**Verification**: `route:list --path=tax -v` confirmed all 27 routes/middleware; direct
`craft exec:exec` invocation of all 3 `edit()` methods confirmed clean `CpScreenResponse`
construction (including exercising the `Cp::chipHtml()`/`getUiLabel()` path that caught the bug
above).

### Laravel Migration — Stage 9b: Controllers & Routes (Shipping)

Migrated `ShippingZonesController`, `ShippingMethodsController`, `ShippingRulesController`,
`ShippingCategoriesController` to `src/Http/Controllers/Settings/`, and `BaseShippingSettingsController`
to a new `Http\Controllers\Concerns\HasStoreManagementScreen` trait (`resolveStore()` +
`storeManagementCpScreen()`, ported 1:1 from `BaseStoreManagementController`'s
`asStoreManagementCpScreen()`/`getStoreSwitcher()`/`getStoreSettingsNav()`) — the shared
store-scoped CP screen chrome needed by every one of stages 9b–9e's controllers. Legacy
`src-yii2/controllers/{BaseShippingSettings,ShippingZones,ShippingMethods,ShippingRules,
ShippingCategories}Controller.php` deleted outright.

- `ShippingRulesController::edit()` is the first controller in this migration confirmed to need
  the plain `pageTemplate()` helper rather than `CpScreenResponse` — its template
  (`shippingrules/_edit.twig`) `{% extends "commerce/_layouts/store-management" %}` and sets its
  own crumbs/tabs in Twig, unlike its sibling `shippingzones|shippingmethods|shippingcategories/
  _edit.twig` (bare `{% block content %}` fragments, no `{% extends %}`) — confirms the
  "check every template" rule from Stage 9a is a real, recurring distinction within the same
  domain, not a one-off.
- Replaced two Yii2 "return void + `setRouteParams()` + implicit re-render" actions
  (`ShippingRulesController::actionSave()`, and `ShippingCategoriesController::
  actionSetDefaultCategory()`'s "return null") with `asModelSuccess()`/`asModelFailure()` or
  `asSuccess()`/`asFailure()`, matching the pattern established in Stage 9a.
- **Found and fixed a real pre-existing bug** while live-verifying: `Shipping\Models\
  ShippingCategory::getUiLabel()` calls the global `t()` helper without importing it
  (`use function CraftCms\Cms\t;` was missing — present on the sibling `ShippingMethod`/
  `ShippingAddressZone` models, just not this one). Latent since whatever earlier stage migrated
  this model, since nothing had exercised `Chippable::getUiLabel()` on a `ShippingCategory`
  until this controller's `index()` wired it into `Cp::chipHtml()`.
- **Verification**: `route:list --path=shipping -v` confirmed all 37 routes/middleware; direct
  `craft exec:exec` invocation of every controller method confirmed correct
  `CpScreenResponse`/string construction with no errors from controller logic (the only failures
  were the established, expected console-context limitation — `craft\console\Request` lacking
  `getSegments()`/a session store, which any real HTTP request has). Full authenticated-browser
  verification not done this pass either, same caveat as Stage 9a.

### Laravel Migration — Stage 9a: Controllers & Routes (foundation + 3 controllers)

First slice of the largest remaining migration stage: 48 Yii2 controllers / 214 `action*()`
methods, currently routed entirely through Yii2's automatic convention + one explicit CP
URL-rule map (`src-yii2/plugin/Routes.php`). Craft 6 has no automatic controller routing and no
base controller class at all, so this is a bigger architectural break than prior stages. This
slice covers the foundation plus three controllers chosen to prove out the three distinct
patterns the remaining ~45 controllers will fall into — full details and what's explicitly
deferred are in `laravel-migration-private.md`.

- Added `routes/{web,cp,actions}.php` — auto-discovered by `CraftCms\Cms\Plugin\Concerns\HasRoutes`.
- Added `Plugin::getPermissions()` (via new `Plugin\Concerns\HasPermissions`), porting the 4
  static permission groups from `src-yii2/Plugin.php::_registerPermissions()` as
  `CraftCms\Cms\User\Data\Permission` objects. `_productPermissions()` (dynamic, per-product-type)
  deferred to whichever session migrates `ProductTypesController`. The legacy registration stays
  active in parallel until every permission is ported.
- Migrated `WebhooksController` (anonymous action-path pattern), `DonationsController` (simple
  CP settings screen), `Settings\GatewaysController` (list-based CRUD CP settings screen — the
  template for ~15 similar controllers) to `src/Http/Controllers/`. **Legacy `src-yii2/controllers/
  {Webhooks,Donations,Gateways}Controller.php` were deleted outright**, not converted to stubs —
  unlike services, nothing in the app resolves controller classes by name anymore (routing is
  100% explicit now), so there's no reason to keep a legacy shim around once a controller is
  migrated.
- **Three load-bearing discoveries, made by reading cms-6's actual source (the public CP-screen
  docs are incomplete) and confirmed live against the running app — future controller
  migrations should rely on these instead of re-deriving them:**
  1. `Plugin::boot()` (standard Laravel `ServiceProvider` lifecycle) is **never called** by
     Craft's plugin system, even though `Plugin extends ServiceProvider` — confirmed by direct
     test (a debug side-effect in `boot()` never ran; the same one in `register()` did). Craft's
     plugin manager calls `register()` and its own internal `bootPlugin()` (which fires the fixed
     `bootHasX()` trait sequence), but not `boot()`. Any one-off plugin-level bootstrapping code
     (e.g. `PreventRequestForgery::except()` for a CSRF-exempt webhook route) needs to go in
     `register()`, not `boot()`.
  2. CP forms built with `actionInput()`/`redirectInput()` (the classic Craft convention, used
     everywhere in Commerce's still-legacy Twig templates) **post back to the current page URL**
     with an `action` body param — they do **not** post to the literal action-route string. A
     global `CraftCms\Cms\Http\Middleware\HandleActionRequest` middleware (registered before
     routing, via `ActionRouteResolver`) rewrites the request's effective URI from that `action`
     param before Laravel's router ever runs. This means a controller's save/archive/reorder
     endpoints reached this way must be registered in `routes/actions.php` (auto-prefixed with
     the plugin handle, and — importantly — auto-registered a second time at the anonymous
     site-side action URL too, so route-level `auth`/`can:` middleware is what actually protects
     them, not the URL), not in `routes/cp.php` at whatever "pretty" URL the page itself lives at.
  3. Two different Twig rendering entry points replace Yii2's `$this->renderTemplate()`,
     depending on the template's own shape: `CpScreenResponse::contentTemplate()` for templates
     that are bare content fragments with no `{% extends %}` (e.g. `commerce/donation/_edit.twig`);
     the plain `pageTemplate()` helper for templates that already `{% extends "commerce/_layouts/
     ..." %}`/build their own full CP page (e.g. `commerce/settings/gateways/{index,_edit}.twig`).
     Using the wrong one either double-wraps the page chrome or renders a bare fragment with none.
- **Known gap, not fixed this session**: `RespondsWithFlash::asModelFailure()` redirects back on
  a failed save rather than Yii2's inline same-request re-render, and Commerce's legacy Twig edit
  templates don't yet read Laravel's `old()`/session-flash data — so a failed `GatewaysController::
  save()` currently redirects back to a blank/default edit form rather than the user's attempted
  input. This is an explicitly-flagged architectural difference (see cms-6's own equivalent
  `VolumesController`/`FilesystemsController`, which have the exact same behavior), not unique to
  this migration — tracked as a follow-up for whichever session addresses form-repopulation UX
  broadly, rather than fixed ad hoc per controller.
- **Verification**: `ddev artisan route:list --path=commerce -v` confirmed all routes/middleware
  registered as designed. The webhook endpoint was verified end-to-end over real HTTP in all
  three reachable forms (pretty URL, site action path, CP action path) — first hit a real CSRF
  419 (fixed via the `register()` discovery above), then a real `TypeError` (`getGatewayById()`
  needs `int`, `$request->input()` returns `string` — fixed with an explicit cast), then
  confirmed correctly returning 404 for an unknown gateway across all three URLs. Unauthenticated
  CP routes correctly redirect to login. `DonationsController`/`GatewaysController`'s controller
  logic and Commerce's own content templates were verified error-free via direct invocation
  through `craft exec:exec`; full authenticated CP-chrome rendering could not be exercised this
  way (Craft's own CP header/layout partials need a real logged-in `currentUser()`, which a
  console context doesn't have) — recommend a real authenticated browser pass before treating
  Donations/Gateways as production-ready, though the routing/CSRF/permission layer underneath is
  proven end-to-end via the webhook test.

### Laravel Migration — CatalogPricingCondition and its rules

Migrated `craft\commerce\elements\conditions\purchasables\{CatalogPricingCondition,
CatalogPricingPurchasableConditionRule,CatalogPricingCustomerConditionRule}` to
`CraftCms\Commerce\CatalogPricing\Conditions\*`, alongside `CatalogPricingConditionRuleInterface`
(already relocated to `src/CatalogPricing/Contracts/` in an earlier stage, now fully
implementable). This fixes a real fatal error discovered while live-verifying the Phase 2
facade cleanup: the legacy `CatalogPricingCondition` overrode `getConfig()`, which Craft 6 made
`final` on the new `CraftCms\Cms\Condition\BaseCondition` — any code path that constructed one
(`CatalogPricing::createCatalogPrices/PricingQuery()`, the catalog pricing CP page, the
purchasable price field's condition builder) fataled outright.

- `defineRules()` → `getRules()`, Illuminate-style. Critically, `allPrices` had to be added as a
  `getRules()` key — under the new `Conditions::createCondition()`, that array doubles as the
  allowlist of properties `['class' => ..., 'allPrices' => ...]` config actually gets applied to
  (Yii2's `Craft::createObject()` did unfiltered property assignment; the new one filters by
  `array_keys($condition->getRules())`). Without this, `allPrices` would have silently stayed
  `false` regardless of what was passed in.
- `modifyQuery(craft\db\Query $query)` → `modifyQuery(Illuminate\Database\Query\Builder $query)`
  on the condition, both rule classes, and the `CatalogPricingConditionRuleInterface` contract —
  the real call site (`src/Services/CatalogPricing.php`) has passed an Illuminate builder for a
  while now (via `DB::table(...)`), so this was a second latent `TypeError` waiting behind the
  `final`-method fatal. Rewrote the Yii2 array-condition/subquery logic (`andWhere([...])`,
  nested `craft\db\Query` subqueries) as Illuminate `where()`/`whereIn()`/closures — verified the
  generated SQL is semantically identical to the original for all three cases (no restriction,
  "no user-specific rules", "rules for a specific customer").
- `CatalogPricingPurchasableConditionRule::modifyQuery()`'s `$query->andWhere(['purchasableId' =>
  $ids])` → `$query->whereIn('purchasableId', $ids)`.
- Dropped the dead `defineRules()` overrides on both rule classes (never called by the new
  validation stack) in favor of real `getRules()` overrides.
- `Craft::t('commerce', ...)` → `t(..., category: 'commerce')` on both rule classes'
  `getLabel()`; `craft\elements\User` → `CraftCms\Cms\User\Elements\User` in the customer rule.
- Left `craft\helpers\{Html,Cp,ArrayHelper}` and `craft\commerce\Plugin`/`base\PurchasableInterface`
  (docblock only) as legacy references — no confirmed-safe new equivalent for the first three
  (not `class_alias`-based; see the craft-core-reference cleanup methodology), and the Commerce
  namespace ones are still a legitimate exception during the transition.
- Legacy `src-yii2/elements/conditions/purchasables/*.php` files are now thin `class_alias`
  stubs. Updated the three still-legacy consumers' imports (`CatalogPricingController.php`,
  `PurchasablePriceField.php`, `src-yii2/services/CatalogPricing.php`) to the new namespace.
- Removed the now-stale "TODO: Migrate ... once conditions migrated" comments and
  `@phpstan-ignore-next-line` markers in `src/Services/CatalogPricing.php`'s three query-building
  methods, now that the types genuinely line up.

**Verification**: `php -l` on all touched/new files; `Conditions::createCondition()`/
`createConditionRule()` round-tripped live via `ddev`/`craft exec:exec` (confirmed `allPrices`
and `customerId` are actually applied, not silently dropped); `CatalogPricing::
createCatalogPricesQuery()` inspected via `->toSql()`/`->getBindings()` for the no-restriction,
"no user rules", and "specific customer" cases, and executed against the real dev database; the
full `getCatalogPrices()` public API path (the one the catalog pricing CP page actually calls)
also verified end-to-end; confirmed the legacy `class_alias` still resolves correctly.

### Laravel Migration — Craft core reference cleanup, Phase 2

Completed the follow-up flagged at the end of Phase 1: swapped `\Craft::$app->getX()->method()`
service-locator calls in already-migrated `src/` code for their real Laravel facade
equivalents, wherever the legacy `craft\services\*` wrapper method was confirmed to be a pure
1:1 delegation. Touched `Catalog/Queries/VariantQuery.php` and 21 files under `Services/`.

- `Craft::$app->getUser()->getIdentity()` → `CraftCms\Cms\currentUserElement()` (13 call sites
  across `Carts`, `Discounts`, `Purchasables`, `Sales`, `Transactions`, `OrderHistories`,
  `VariantQuery`) — this is the documented Craft 6 replacement (see `Auth::craftUser()` /
  `request()->craftUser()`), using the `?->asElement()`-returning helper since every call site
  needed the `User` element itself (`->id`, `->email`, `->getGroups()`, `->can()`, or a typed
  `?User` param), not just the `CraftUser` interface.
- `Craft::$app->getElements()->{saveElement,deleteElementById,getElementById,duplicateElement,
  createElementQuery,getElementTypeById}()` → `CraftCms\Cms\Support\Facades\Elements`
- `Craft::$app->getElements()->{invalidateCachesForElement,invalidateCachesForElementType}()` →
  `CraftCms\Cms\Support\Facades\ElementCaches::{invalidateForElement,invalidateForElementType}()`
  — this is the real fix for the trap flagged in Phase 1: the legacy method doesn't call the
  `Elements` service at all, it routes through a completely different `ElementCaches` object, so
  the correct swap targets a different facade with renamed methods, not `Elements::` directly.
- `Craft::$app->getProjectConfig()->{set,remove,get,processConfigChanges}()` →
  `CraftCms\Cms\Support\Facades\ProjectConfig` (also `Craft::$app->projectConfig->...` property
  access, same underlying object)
- `Craft::$app->getSites()->{getCurrentSite,getPrimarySite,getAllSites,getSiteById,getSiteByUid,
  getHasCurrentSite,setCurrentSite}()` → `CraftCms\Cms\Support\Facades\Sites` — the legacy
  read methods actually return a re-wrapped `craft\models\Site` (different object, and `array`
  instead of `Collection` for `getAllSites()`), which is normally unsafe to swap blindly, but
  every call site in Commerce only ever reads `->id`/`->handle`/`->uid` off the result, which
  exist identically on the new `Site\Data\Site`, so the swap is behaviorally identical here.
- `Craft::$app->getIsMultiSite()` → `Sites::isMultiSite()`
- `Craft::$app->getUsers()->{getUserById,assignUserToDefaultGroup}()` →
  `CraftCms\Cms\Support\Facades\Users`
- `Craft::$app->getFields()->{deleteLayoutsByType,getLayoutByType,saveLayout}()` →
  `CraftCms\Cms\Support\Facades\Fields`
- `Craft::$app->getConditions()->{createCondition,createConditionRule}()` →
  `CraftCms\Cms\Support\Facades\Conditions`
- `Craft::$app->getPlugins()->{isPluginInstalled,getStoredPluginInfo}()` →
  `CraftCms\Cms\Support\Facades\Plugins`
- `Craft::$app->getPath()->{getTempPath,getCachePath,getLogPath}()` →
  `CraftCms\Cms\Support\Facades\Path::{temp,cache,logs}()` (renamed methods)
- `Craft::t('app'/'commerce', ...)` → `t(..., category: 'app'/'commerce')` in `Customers.php`

**One more confirmed trap, left untouched on purpose**: `Craft::$app->getUsers()->
sendActivationEmail()` in `Customers.php` does not delegate to the new `Users` service's
method of the same name — the legacy path calls the generic `$user->sendEmailVerificationNotification()`,
while the new service sends a distinct `ActivationNotification` with an explicitly-generated
verification code. Swapping would change which email gets sent, so this one stays on the
legacy service-locator call.

**Discovered while verifying, unrelated to this change, not fixed**: instantiating the legacy
`craft\commerce\elements\conditions\purchasables\CatalogPricingCondition` (via either the old
`Craft::$app->getConditions()->createCondition()` or the new `Conditions::createCondition()` —
confirmed identical on both paths) currently fatals with "Cannot override final method
`CraftCms\Cms\Condition\BaseCondition::getConfig()`", since Craft 6 made that method `final` and
the legacy condition class still overrides it. This blocks any code path that constructs a
`CatalogPricingCondition`/`CatalogPricingCustomerConditionRule` (e.g.
`CatalogPricing::createCatalogPrices/PricingQuery()`) until those condition classes are migrated
off the legacy base — tracked as part of the already-known "migrate to new element conditions
system" TODOs in that file, not introduced by this change.

**Verification**: all touched files pass `php -l`; live-verified via ddev (`craft exec:exec`)
that every affected service still boots and its core methods still execute correctly against
real data — `Sites`, `Stores`, `Products`, `Variants`, `Purchasables`, `Sales`, `Discounts`,
`CatalogPricingRules`, `Emails`, `Pdfs`, `Gateways`, `LineItemStatuses`, `OrderStatuses`,
`Subscriptions`, `Inventory`, `Orders`, plus the legacy `Plugin::getInstance()->getX()` alias
paths (to catch alias-timing issues) and a real `ElementCaches`/`Elements` round trip against an
existing order.

### Laravel Migration — Craft core reference cleanup, Phase 1

Fixed the "genuine violations" from the earlier craft-core-reference audit: already-migrated
`src/` files still importing old `craft\*` core classes where a real `CraftCms\Cms\*`
equivalent now exists, across `Catalog/Models/CatalogPricingRule.php`, `Helpers/ProductQuery.php`,
and `Services/{CatalogPricingRules,Customers,Discounts,Emails,Orders,OrderHistories,
Purchasables,Sales,Stores,Subscriptions}.php`:

- `craft\elements\{User,Address,Entry,ElementCollection}` → `CraftCms\Cms\{User\Elements\User,Address\Elements\Address,Entry\Elements\Entry,Element\ElementCollection}`
- `craft\models\FieldLayout` → `CraftCms\Cms\FieldLayout\FieldLayout`
- `craft\models\Site` → `CraftCms\Cms\Site\Data\Site`
- `craft\errors\{ElementNotFoundException,InvalidElementException,UnsupportedSiteException}` → `CraftCms\Cms\Element\{Queries\Exceptions\ElementNotFoundException,Exceptions\InvalidElementException,Exceptions\UnsupportedSiteException}`
- `craft\helpers\DateTimeHelper::toDateTime()` → `CraftCms\Cms\Support\DateTimeHelper::toDateTime()`
- `craft\helpers\ElementHelper::cleanseQueryCriteria()` → `CraftCms\Cms\Element\ElementHelper::cleanseQueryCriteria()`
- `craft\helpers\Assets::tempFilePath()` → `CraftCms\Cms\Asset\AssetsHelper::tempFilePath()`

**Two more corrections to the original audit**, on top of the `FieldLayout` one already
caught in Stage 7e: `craft\models\Site` was assumed to alias `Site\Models\Site` but actually
aliases `Site\Data\Site` (confirmed via `yii2-adapter/src/ClassAliases.php`), and
`craft\helpers\Assets` was assumed to have a facade equivalent, but the legacy `Assets` class
is a plain static helper (`class Assets extends CraftCms\Cms\Asset\AssetsHelper`), not a
facade proxy — the actual target is `AssetsHelper`, imported `as Assets` to keep call sites
unchanged.

**Also discovered the audit's proposed fixes weren't uniformly safe at the method level**:
`craft\helpers\DateTimeHelper` and `ElementHelper` are not `class_alias`-based (unlike the
element/model/exception swaps above, which are genuine aliases and therefore risk-free) — they're
real subclasses in `yii2-adapter/legacy/helpers/` extending the new classes. Some methods are
purely inherited (safe to call via the new class directly — `toDateTime()`,
`cleanseQueryCriteria()`, `tempFilePath()`); others exist *only* on the legacy subclass
(`DateTimeHelper::now()`, `::secondsToInterval()`, `::currentUTCDateTime()`, still used in
`Carts.php`/`Plans.php`) and have no new-class equivalent at all — left untouched rather than
guessed at.

**Phase 2 not started**: `\Craft::$app->getX()->method()` → Facade swaps (`Elements`,
`ProjectConfig`, `Sites`, `Users`, `Fields`, `Plugins`, `Path`, `Conditions` all have real
facades). This category needs the same per-method verification as above, since the legacy
`craft\services\*` wrapper classes are hand-written compat shims, not simple aliases or
inheritance — confirmed one real trap already: `craft\services\Elements::invalidateCachesForElementType()`
does not delegate to what would be the obvious facade method, it calls a completely different
internal object (`$this->elementCaches()->invalidateForElementType()`). See
`laravel-migration-private.md`'s craft-core-reference follow-up for the full verification
procedure before attempting this phase.

**Verification**: all touched files pass `php -l`; live-verified via ddev that every affected
service still boots and its core methods still execute correctly (`Sales::getAllSales()`,
`Stores::getAllStores()`, etc.) after the import swaps.

### Laravel Migration — Events layer: missing-constructor bug fix

Fixed the cross-cutting bug flagged as a HIGH PRIORITY follow-up during Stage 7d/7e:
of the 56 event classes moved to `src/*/Events/*.php` in Stage 3, only 4 had a real
constructor (the `Catalog\Events\Customize*SnapshotEvent` classes, fixed as part of
Stage 7d because that stage's own code constructed them). The other 51 were bare
classes with no `__construct()` at all — a holdover from dropping the Yii2 `yii\base\Event`
base class (whose `BaseObject` ancestor supplied a config-array constructor) without
replacing the call sites that still constructed events Yii2-style:
`new SomeEvent(['prop' => $val])`. PHP does not error when you pass a constructor
argument to a class with no declared `__construct()` — the array is silently discarded.
A required typed property then throws "must not be accessed before initialization"
the instant any listener reads it; a property with a default just silently keeps that
default forever. This was live in already-shipped code (e.g. every `Order.php`
`LineItemEvent` trigger from Stage 7b).

**Fix**: every affected event class got a real constructor with PHP 8 promoted
properties, matching its exact original types/nullability/defaults, and every call
site (both the `new X([...])` array-config pattern and the `new X(); $x->prop = ...;`
empty-then-assign pattern) was updated to named-argument construction. ~100 call
sites across ~35 consumer files, plus the 51 event classes themselves.

Found and fixed 3 more latent issues while verifying:
- `DefaultOrderStatusEvent::$orderStatus` was declared non-nullable `OrderStatus`, but
  `OrderStatuses::getDefaultOrderStatusForOrder()` (whose own return type is already
  `?OrderStatus`) can legitimately pass `null` when a store has no default order
  status configured. Previously this was invisible (the broken constructor silently
  dropped it either way); now it's a real, avoidable `TypeError` waiting to happen.
  Widened to `?OrderStatus`.
- `SaleEvent`/`SaleMatchEvent`/`TransactionEvent`/`RefundTransactionEvent`/
  `ProcessPaymentEvent`/`PaymentCurrencyRateEvent` imported the legacy aliased
  `craft\commerce\models\{Sale,Transaction}` instead of the already-migrated
  `CraftCms\Commerce\{Promotion,Payment}\Models\{Sale,Transaction}` — a
  Guiding-Principle-9 alias-timing risk, same class of issue as Stage 7c/7d, caught by
  phpstan flagging a type mismatch between the constructor's declared param type and
  what every real call site actually passes. Updated both files' imports.
- `src/Services/ProductTypes.php` (Stage 7e) constructed `ProductTypeEvent` via its
  fully-qualified legacy alias (`new \craft\commerce\events\ProductTypeEvent()`)
  instead of importing the new namespace directly — fixed to match this project's
  established convention.

**Verification**: confirmed via reflection that all 51 classes have a real,
parameter-bearing constructor (not an inherited empty one, except `DeleteStoreEvent`
which correctly inherits `StoreEvent`'s). Confirmed end-to-end through the real
dispatch pipeline: registered a Laravel `Event::listen(SaleEvent::class, ...)`
listener, called `Sales::saveSale()`, and confirmed the captured event object carried
a fully-populated, correctly-typed `Sale` instance and correct `isNew` — proving the
fix works through actual application code, not just in isolation. Also discovered
along the way that different already-migrated services use two different event-firing
mechanisms — `Sales.php` dispatches natively via Laravel's `event()` helper, while
`LineItems.php`/`ProductTypes.php` still bridge through the legacy Yii2 component's
`trigger()` (documented as a `// TODO: migrate event firing to Laravel` in each) —
both are equally valid for this fix, but it's worth knowing the two patterns coexist
when doing future event-related work.

### Laravel Migration — Stage 7e: ProductType

Migrated `craft\commerce\models\ProductType` and `craft\commerce\records\{ProductType,ProductTypeSite}`
to `CraftCms\Commerce\Catalog\ProductType\{Data,Models}\*`, and `craft\commerce\services\ProductTypes`
to `CraftCms\Commerce\Services\ProductTypes`. This was the piece deliberately deferred out of Stage 7d:
`ProductType` needs **two** independent field layouts (`Product`'s custom fields and `Variant`'s), but
`CraftCms\Cms\FieldLayout\Concerns\HasFieldLayout` only supports one field layout per host class.

**The dual-field-layout design.** Legacy solved this with two independently-configured Yii2
`FieldLayoutBehavior` instances attached under different names. Rather than build two small
standalone "provider" objects each using `HasFieldLayout` (which would have required its
closure-based `setFieldLayoutId(callable)` configuration path — confirmed via a full search of
cms-6 to have zero real-world usage anywhere, making it an untested pattern to lean on), the new
`ProductType` hand-rolls two independent getter/setter pairs
(`get/setProductFieldLayout()`, `get/setVariantFieldLayout()`), each resolving its own
`FieldLayout` and setting `$fieldLayout->provider = $this`. This mirrors the trait's own ~15 lines
of real logic closely enough to diff cleanly against the legacy behavior-based version, and avoids
introducing an unproven pattern. `ProductType` implements
`CraftCms\Cms\FieldLayout\Contracts\FieldLayoutProviderInterface` (its `getFieldLayout()` aliases
to `getProductFieldLayout()`, matching the legacy interface method) — this also resolves a
phpstan-only type mismatch from Stage 7d, where `Variant.php` sets `$fieldLayout->provider =
$productType` but legacy `ProductType` only implemented the *old* `craft\base\FieldLayoutProviderInterface`.

**Data/Model split**, same as `LineItem` (Stage 7c) and cms-6's own `Entry\Data\EntryType` /
`Entry\Models\EntryType`: `Catalog\ProductType\Data\ProductType` (rich `Component`, validation,
computed field layouts, CP URLs) + `Catalog\ProductType\Models\ProductType` (thin Eloquent,
persistence only). `commerce_producttypes.id` is a genuine auto-increment column (unlike
`Product`/`Variant`/`Order`/`Donation`), so no `$incrementing = false` override needed here.
`ProductTypeSite` (migrated in an earlier stage as a Data-only `Component`, with a `// TODO:
migrate to app(ProductTypes::class)...` marker) got its missing thin Eloquent counterpart —
`Catalog\ProductType\Models\ProductTypeSite` — and the TODO was resolved.

Validation moved from Yii2 `HandleValidator`/`UniqueValidator` configs to the modern pattern
already established in cms-6 (`CraftCms\Cms\Validation\Rules\HandleRule` +
`Illuminate\Validation\Rule::unique(...)->ignore(...)`, matching `CraftCms\Cms\Entry\Validation\EntryTypeRules`)
rather than porting the legacy validator classes. The imperative validators (field layout
validation, preview targets) stayed as plain methods wired through `afterValidate()`, matching the
`OrderRules`/`ProductRules` precedent.

**Bugs found and fixed while verifying live** (none introduced by this stage — all three were
latent, only surfaced by exercising the actual save-and-persist path):

- **`getAttributes()` passthrough threw for `ProductTypeSite`.** Unlike `ProductType` itself
  (every Eloquent column has a same-named Data property), `ProductTypeSiteRecord`'s attributes
  include `dateCreated`/`dateUpdated`/`uid`, none of which the `ProductTypeSite` Data class
  declares. `Component::__set()` throws `UnknownPropertyException` for genuinely undeclared
  properties (it does **not** silently ignore them the way Yii2's config-array constructor does) —
  confirmed live, not just by re-reading `Typecast::configure()`'s source. Fixed by hydrating
  `ProductTypeSite` via explicit property assignment, same fix pattern as `LineItem` in Stage 7c.
- **Site-settings records need explicit `dateCreated`/`dateUpdated`.** `ProductTypeSiteRecord` has
  `$timestamps = false` (matching the established thin-model convention), so nothing set those
  `NOT NULL` columns on insert — the DB rejected the row (`Field 'dateCreated' doesn't have a
  default value`). Fixed by setting both explicitly before `save()`, matching how the main
  `ProductType` record already did it.
- **`craft\events\ConfigEvent` (legacy), not the new `CraftCms\Cms\ProjectConfig\Events\ConfigEvent`,
  is what `handleChangedProductType()`/`handleDeletedProductType()` actually receive at runtime.**
  This one is a correction to this project's own tracking, not just this stage's code — see
  `laravel-migration-private.md`'s craft-core-reference follow-up for the full explanation:
  `Craft::$app->getProjectConfig()` (as called from still-legacy `Plugin.php`) resolves to the
  legacy `craft\services\ProjectConfig` wrapper, whose `onAdd()`/`onUpdate()`/`onRemove()`
  subscribe to the new project config system internally but **reconstruct a legacy `ConfigEvent`**
  before invoking the registered handler. Confirmed live (`TypeError` when type-hinting the new
  class) and by cross-checking already-shipped `Gateways::handleChangedGateway()`, which already
  uses the old type and works correctly — it was never a bug.

**Verification.** Live via `php craft exec:exec` (ddev). Confirmed: legacy aliases resolve for the
model, both records, and the exception class; field layout resolution returns real `FieldLayout`
objects with the correct `provider` for both product and variant layouts; `getConfig()`,
`validate()`, `getSiteSettings()`, `getShippingCategories()`/`getTaxCategories()` all work; a full
`saveProductType()` round trip through the real project-config event pipeline (not a direct method
call) correctly persisted a new product type plus **both** independent field layouts (with real,
distinct IDs) and its site settings, then `deleteProductTypeById()` cleanly removed everything with
no orphaned rows. `ProductTypesController.php`'s two `getBehavior(...)->setFieldLayout(...)` call
sites (the only writer of in-memory field layouts) updated to the new `setProductFieldLayout()`/
`setVariantFieldLayout()` methods.

### Laravel Migration — Stage 7d: Product & Variant

Migrated `craft\commerce\elements\{Product,Variant}`, their element queries,
and their records to `CraftCms\Commerce\Catalog\{Elements,Queries,Models}\*`.
`ProductType` stays fully legacy for now (Stage 7e) — Product/Variant only
ever consume it through its public getters, never a hard dependency.

`Variant` extends the abstract `CraftCms\Commerce\Purchasable\Elements\Purchasable`
base built in Stage 7a for `Donation` — that base turned out to already be a
complete superset of the legacy `craft\commerce\base\Purchasable` (confirmed
by diffing every method name between the two), including special-casing
`NestedElementInterface` in `afterSave()` for exactly the draft/revision
ownership-transfer logic `Variant` needs, even though only non-nested
`Donation` used it until now. No changes were needed to that base file.
`craft\commerce\base\Purchasable` (the legacy 1599-line abstract base) is now
a `class_alias` stub, since `Variant` was its last extender.

**Bugs found and fixed while verifying the save cycle live** (none were
introduced by this stage — all three predate it and were latent because
nothing had exercised these paths with real data yet):

- **Eloquent `id` silently reset to `0` after insert.** `commerce_products.id`,
  `commerce_variants.id` (and, discovered by extension, `commerce_orders.id`
  and `commerce_donations.id`) are foreign keys to `elements.id`, not
  auto-increment columns. Eloquent defaults every model to
  `$incrementing = true`, so after `INSERT` it overwrites the model's `id`
  with `$pdo->lastInsertId()` — which MySQL returns as `0` for a table with no
  auto-increment column. `Product::afterSave()` then propagated that `0` back
  onto the element itself (`$this->id = $record->id`), corrupting the owner's
  ID before nested variant saves ran. `Order`/`Donation` never hit this
  because neither reads the record's `id` back after `save()` — same
  underlying defect, just never triggered. Fixed by adding
  `public $incrementing = false;` to `Catalog\Models\{Product,Variant}`,
  `Order\Models\Order`, and `Purchasable\Models\Donation`.
- **`Variant::availableShippingCategories()` called `->pluck()` directly on a
  plain `array`.** `ShippingCategories::getShippingCategoriesByProductTypeId()`
  returns `array`, not a `Collection` — needed a `collect()` wrapper, matching
  the (correct) tax-category equivalent five lines below it.
- **Event classes instantiated with a Yii2 config array silently do nothing.**
  `new CustomizeProductSnapshotFieldsEvent(['product' => ..., 'fields' => ...])`
  compiles and runs without error, but a plain PHP class with no constructor
  ignores constructor arguments entirely — properties keep their defaults, and
  reading an uninitialized typed property (e.g. `$event->product`) later
  throws. The original Yii2 class extended `yii\base\Event`, which supplied
  the config-array constructor; that behavior was lost when the class was
  migrated to `src/` in Stage 3, but nothing updated the call site. Fixed the
  4 events `Variant::getSnapshot()` uses
  (`Customize{Product,Variant}Snapshot{Fields,Data}Event`) with real
  constructors (promoted properties, matching cms-6's own event convention
  like `ProjectConfig\Events\ConfigEvent`), and updated the call sites to
  named-argument construction.
  ⚠️ **This same defect affects the rest of the Events layer** — of the 56
  classes moved to `src/*/Events/` in Stage 3, only the 4 fixed here now have
  a real constructor. The rest (`Order\Events\LineItemEvent`,
  `Catalog\Events\ProductEvent`, etc.) still silently discard their
  constructor array and will throw "must not be accessed before
  initialization" the moment a listener reads a required property. This is
  **not** new to this stage — it predates it — but wasn't caught until a real
  save cycle exercised one of these event triggers. Confirmed already present
  in shipped code: `Order.php`'s `EVENT_AFTER_ADD_LINE_ITEM`/
  `EVENT_AFTER_REMOVE_LINE_ITEM`/etc. triggers all construct `LineItemEvent`
  the same broken way. Needs a dedicated remediation pass across
  `src/*/Events/*.php` plus every call site — tracked as a follow-up in
  `laravel-migration-private.md`, not fixed here beyond this stage's own
  scope.

**Verification.** No working Codeception harness in this environment; used
live `php craft exec:exec` calls via ddev. Confirmed: legacy aliases resolve
correctly (`Product`, `Variant`, `base\Purchasable`, records, queries);
`Product::find()`/`Variant::find()` return the new query classes and execute
correctly with 15+ query-parameter combinations; a full save cycle (product +
nested variant, with the CP's `variants` dirty-attribute marker set
manually since there's no CP UI to drive it) round-tripped correctly after
the fixes above, including `defaultVariantId`/`defaultSku`/`defaultPrice`
propagation and SKU-uniqueness/min-max-qty validation; `getSnapshot()`
verified after the event-constructor fix. One save-cycle path (URI generation
against a product type with a real `uriFormat`) is blocked by a pre-existing,
confirmed-unrelated CMS-core bug — `renderObjectTemplate()` reliably throws
"Cannot redeclare class CraftCms\Cms\Section\øSections" on first render in
this environment, reproduced with a bare `Template::renderObjectTemplate()`
call with zero Commerce code involved. Same class of issue noted in Stage 7a;
not fixed here (out of scope, cms-6 core).

### Laravel Migration — Stage 7c: LineItem

Migrated `craft\commerce\models\LineItem` (business logic) and
`craft\commerce\records\LineItem` (persistence) to `CraftCms\Commerce\Order\LineItem\*`,
and `craft\commerce\services\LineItems` to `CraftCms\Commerce\Services\LineItems`.

**Not a unified Eloquent class, unlike `Order`.** The first draft merged both
into one Eloquent model, reasoning that — unlike `Order`, which had to split
into `Elements\Order` + `Models\Order` because an Element can't also extend
Eloquent — `LineItem` isn't an Element, so there's no equivalent forced split.
That draft was wrong: it broke a real behavioral guarantee. Yii2's `Model`
routes bare property access (`$lineItem->price`) through a same-named
`getPrice()` method; Eloquent's `__get()` does not — it only consults real
attributes/casts/accessors, never an arbitrary `getPrice()` method. Legacy
adjusters (`Tax.php`, `Discount.php`, still fully unmigrated) and the
already-migrated `Order::recalculate()` all read computed values like
`$item->salePrice` as bare properties, and `Purchasable`/`Donation`'s
`populateLineItem()` *writes* `$lineItem->price = ...` expecting `setPrice()`'s
side effect (clearing a cached sale price) to run. On Eloquent, both of those
would have silently done the wrong thing — reading/writing the raw DB column
instead of the computed value — with no error to signal it.

The fix, following the `Entry\Data\EntryType` / `Entry\Models\EntryType` split
in cms-6: `Order\LineItem\Data\LineItem` is the rich business object (a
`Component`, like the legacy model was a `Model` — same bare-property-routes-
through-getter guarantee), and `Order\LineItem\Models\LineItem` is a genuinely
thin Eloquent model used only for persistence. The `LineItems` service bridges
the two: reads hydrate a `Data\LineItem` field-by-field from the Eloquent row
(deliberately *not* passing `$record->getAttributes()` into the constructor —
several persisted columns, like `optionsSignature`/`salePrice`/`subtotal`/
`total`/`promotionalAmount`, back pure computed getters with no setter on the
Data object, so passing them as constructor config throws "Setting read-only
property"); saves find-or-create the Eloquent row and copy the Data object's
current attributes onto it.

Other notable decisions:
- FK columns (`orderId`, `purchasableId`, `taxCategoryId`,
  `shippingCategoryId`, `lineItemStatusId`) resolve to an Element (`Order`),
  other Elements (`Purchasable`/`Donation`), or `Component`-based Models
  (`TaxCategory`, `ShippingCategory`, `LineItemStatus`) — none of them plain
  single-table Eloquent models — so `getOrder()`/`setOrder()`,
  `getPurchasable()`/`setPurchasable()`, `getTaxCategory()`,
  `getShippingCategory()`, `getLineItemStatus()`/`setLineItemStatus()` stay
  hand-written getters/setters with private memoization caches, exactly like
  the legacy model; no native Eloquent relations were attempted anywhere.
- `CurrencyAttributeBehavior` (Yii2, not portable) is replaced with 11
  explicit `get*AsCurrency()` methods, mirroring `Purchasable`/`Order`.
- The thin Eloquent model needs explicit numeric/boolean/JSON casts for every
  column — MySQL returns `DECIMAL` columns as strings via PDO by default, and
  the Data object's properties are strictly typed (`float`/`int`), so an
  uncast `weight`/`price`/etc. throws a `TypeError` the moment a fetched row
  gets copied across.
- Dropped 5.x-deprecated API after confirming no call sites anywhere in
  `src/` or `src-yii2/`: `LineItem::getSaleAmount()`, `refreshFromPurchasable()`,
  `populateFromPurchasable()`, `getOnSale()`, and `LineItems::createLineItem()`.
- Kept legacy: `craft\commerce\errors\StoreNotFoundException` (no migrated
  equivalent yet); `craft\commerce\records\TaxRate::TAXABLE_*` constants (the
  migrated `Tax\Models\TaxRate` didn't carry these over). Event firing still
  bridges through `Plugin::getInstance()->getLineItems()` pending the Laravel
  event-system bridge, matching `Purchasables`/`Inventory`/`LineItemStatuses`.

Found and fixed three more real bugs while wiring this in:
- **A genuine circular class-alias load.** `PurchasableInterface::afterOrderComplete()`
  was updated to type-hint the new `Order`/`LineItem` directly, but the legacy
  `craft\commerce\base\Purchasable` (still `Variant`'s parent, untouched and
  deferred) implements that same interface and still imported the *old*
  `Order`/`LineItem` names for its own copy of that method signature. Having
  the interface and one of its implementers reference the same class via two
  different names sent PHP into "During inheritance of X, while autoloading
  Y" — a real fatal, not just a `TypeError` — the moment anything touched
  `Variant`. Fixed by pointing the legacy base's imports at the new
  namespaces directly (a trivial, behavior-neutral import swap, since both
  names are the same class either way).
- **`Order.php`'s `_saveLineItems()`** used `LineItemRecord::find()->where(...)->all()`
  (ActiveRecord) to diff previous-vs-current line items and `$previousLineItem->delete()`
  to remove stale ones — neither exists on the new Eloquent model. Rewritten
  to use `app(LineItems::class)->getAllLineItemsByOrderId()` (which conveniently
  already returns rich `Data\LineItem` objects, removing the need for a
  second `getLineItemById()` call to build the removal event's payload) and
  `DB::table(Table::LINEITEMS)->where('id', ...)->delete()`.
- **The same `class_alias`-timing `TypeError` from Stage 7b's Guiding
  Principle 9**, hit again — this time via the legacy `LineItems` service
  wrapper's own method signatures (`resolveLineItem(): LineItem` etc.),
  which resolved `LineItem`/`Order` through their old aliased names. Fixed
  by having the wrapper import the new namespaces directly instead, same as
  every other fix of this kind. Confirmed (but did not fix, out of scope)
  that most other legacy service wrappers from earlier stages have the same
  latent exposure via `use craft\commerce\elements\Order;` — it doesn't
  surface in normal request flows because Craft's own bootstrap touches the
  real `Order` class early via element-type registration, so the alias is
  already warm by the time application code runs; it only bites isolated
  scripts that touch a legacy wrapper as the first-ever reference to a given
  aliased class in that PHP process.

Verified live via `php craft exec:exec`: confirmed bare property access
(`$lineItem->price`, `$lineItem->salePrice`) correctly routes through the
computed getters on both a fresh and a refetched `Data\LineItem`; a full
new-order → resolve a line item via both the new and legacy service →
add → save → refetch → totals cycle; removing a line item and resaving to
exercise the rewritten deletion branch; and the legacy `Plugin::getInstance()->getLineItems()`
wrapper end-to-end. `Order` (`Elements\Order`) was the last consumer still on
the legacy `models\LineItem`/`records\LineItem`, exactly as flagged when
Order was migrated in Stage 7b — it's now fully on the new namespace.

### Laravel Migration — Stage 7b: Order element

Migrated `craft\commerce\elements\Order` (4026 lines, plus the three traits it
composed — `OrderElementTrait`, `OrderNoticesTrait`, `OrderValidatorsTrait`,
folded directly into the class rather than kept as separate files since
nothing else used them), its query, and its record to
`CraftCms\Commerce\Order\Elements\Order`,
`CraftCms\Commerce\Order\Queries\OrderQuery`, and a new Eloquent
`CraftCms\Commerce\Order\Models\Order` (replacing the ActiveRecord). Also
migrated the 4 small `errors\*` exception classes to `Order\Exceptions\`
(dropping the Yii2 `getName()` mechanism, which incidentally removes
`OrderAdjustmentNotFoundException::getName()`'s copy-paste bug — it returned
"Line Item not found").

`LineItem` and its record stay legacy throughout (deferred to the next
stage — Order still type-hints `craft\commerce\models\LineItem`/
`records\LineItem` everywhere), as does the abstract `base\Gateway` class and
`Plugin::getInstance()->getLineItems()`. Every other `Plugin::getInstance()->getX()`
call was swapped for `app(CraftCms\Commerce\Services\X::class)` since all of
Order's other service dependencies are already migrated (Stage 6).
`CurrencyAttributeBehavior` (a Yii2 behavior, not portable to Laravel) is
replaced with explicit `*AsCurrency` getters, mirroring the pattern already
established on `Purchasable` in Stage 7a; the 9 imperative validators from
`OrderValidatorsTrait` became plain methods wired through `afterValidate()`/
`prepareForValidation()` rather than forced into declarative Illuminate
rules, since they mutate notices and nested-model errors under dotted
attribute keys.

Drafted via two parallel agents (element+validation/exceptions, and the
query), then reviewed and fixed by hand. Found and fixed several real bugs
during review and live verification:
- `Order::find()` still returned the **legacy** `OrderQuery` (a stale
  import left over from drafting the element before the new `OrderQuery`
  existed) — completely defeating the query migration. Fixed to return the
  new one.
- `Order`'s own `init()`/`beforeValidate(): bool` overrides used Yii2
  lifecycle hooks that don't exist on the new `Element` base at all (it has
  no `init()`; the pre-validation hook is `prepareForValidation(): void`,
  Illuminate-style) — both `#[Override]` attributes would have been a hard
  compile error. Converted `init()`'s defaulting logic into a `__construct()`
  override (run after `parent::__construct($config)`), and renamed
  `beforeValidate()` to `prepareForValidation()`.
- Two more instances of the Stage 7a "which `Purchasable` base is this"
  bug: `lineItemsByPurchasable()` was typed against the new `Purchasable`
  base only (rejecting `Donation` was fine, but would've rejected
  `Product`/`Variant` once they're real args) — widened to
  `PurchasableInterface`; `afterDelete()`'s stock-cache-refresh check
  `instanceof Purchasable` only matched the *new* base, so it silently
  stopped updating stock caches for anything still on the legacy base
  (i.e. every real Product/Variant order) — widened to check both.
- `Store::getCurrency()` returns a `Money\Currency` object (this changed
  when `Store` was migrated in Stage 5k), but `Order::$currency` and
  `$_paymentCurrency` are plain `?string` ISO-code properties — the ported
  `init()`/`getPaymentCurrency()` logic assigned the object directly,
  which would `TypeError` on the very first order without an explicit
  currency. Both now call `->getCurrency()?->getCode()`.
- Several already-migrated `Order\Events\*` classes (`DefaultOrderStatusEvent`,
  `OrderNoticeEvent`, `DefaultLineItemStatusEvent`, `OrderStatusEmailsEvent`,
  `CartEvent`, `OrderStatusEvent`, `ModifyCartInfoEvent`) were migrated back
  in Stage 3, before `OrderStatus`/`OrderNotice`/`LineItemStatus`/`Order`
  itself existed in the new namespace, and still type-hinted the legacy
  classes. Since `class_alias` doesn't reliably satisfy `instanceof`/
  parameter-type checks for a name that's never been referenced yet in a
  given PHP process (confirmed independent of these changes — reproduces
  identically assigning a freshly-migrated `TaxRate` to a legacy-typed
  property elsewhere in the codebase), a real order-completion call threw
  a `TypeError` the first time `getDefaultOrderStatusForOrder()` populated
  one of these events. Updated all of them to the new namespaces.
- Same latent bug, hit directly rather than via an Event: `src-yii2/adjusters/Tax.php`
  (fully legacy, untouched by this migration otherwise) type-hinted the
  legacy, aliased `models\TaxRate` while the already-migrated `TaxRates`
  service hands it a `CraftCms\Commerce\Tax\Models\TaxRate` instance
  directly — same alias-timing TypeError, blocking every order recalculation
  that hits a tax rate. Repointed the import at the real class.
- `Customers::orderCompleteHandler()`/`savePrimaryAddressesFromOrder()`
  (both already-migrated, Stage 6i) called `OrderRecord::updateAll(...)` —
  a Yii2 ActiveRecord static method with no Eloquent equivalent — against
  what was about to become the new Eloquent `Order` model. Converted both
  to `OrderRecord::query()->where(...)->update([...])`.

Verified live via `php craft exec:exec` against the dev database: fetch
through the new `OrderQuery`, notice add/clear, a full new-order → add a
real line item (a Stage 7a `Donation`) → save → refetch → totals →
`markAsComplete()` → paid-status cycle, plus confirming the legacy alias and
`Plugin::getInstance()->getOrderStatuses()` paths still resolve correctly.
One piece of `markAsComplete()` — reference-number generation via
`renderObjectTemplate()` — could not be verified in this environment: it
throws a `Cannot redeclare class CraftCms\Cms\Section\øSections` fatal
error, but this reproduces identically calling `renderObjectTemplate()`
against the already-shipped, unrelated `Donation` element, confirming it's
a pre-existing CMS-level bug independent of this work, not something this
stage introduced.

Also noticed but out of scope for this stage: `TaxRatesController.php` has
the same stale `models\TaxRate` import as the `Tax` adjuster did — not fixed
since it isn't on Order's execution path and wasn't blocking verification.

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
  **Correction (added during Stage 7d):** this was not actually completed. Of the 56 event classes moved in this stage, only 4 have a real constructor as of Stage 7d (the ones Stage 7d itself fixed). The other ~52 are still bare classes with no constructor at all, and most call sites (including already-shipped ones, e.g. `Order.php`'s `LineItemEvent` triggers from Stage 7b) still construct them with a Yii2-style array, which silently does nothing — see the "Events layer silently discards its constructor data" follow-up in `laravel-migration-private.md`.
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
