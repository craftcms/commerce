<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use Closure;
use craft\base\Event as YiiEvent;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use craft\commerce\services\Emails as LegacyEmails;
use craft\commerce\services\Gateways as LegacyGateways;
use craft\commerce\services\OrderAdjustments as LegacyOrderAdjustments;
use craft\commerce\services\Purchasables as LegacyPurchasables;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Auth\Events\ElementAuthorizing;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Events\DefineDeletionBlockers;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlEagerLoadableFieldsResolving;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlArguments;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Twig;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Path;
use CraftCms\Cms\Support\Typecast;
use CraftCms\Cms\SystemMessage\Models\SystemMessage;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Cms\User\Events\UserAssignedToGroups;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\FieldLayoutElements\ProductTitleField;
use CraftCms\Commerce\Catalog\FieldLayoutElements\VariantsField as VariantsLayoutElement;
use CraftCms\Commerce\Catalog\FieldLayoutElements\VariantTitleField;
use CraftCms\Commerce\Catalog\Fields\Products as ProductsField;
use CraftCms\Commerce\Catalog\Fields\Variants as VariantsField;
use CraftCms\Commerce\Catalog\LinkTypes\ProductLinkType;
use CraftCms\Commerce\Catalog\Products;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Console\Commands\ExampleTemplates\ExampleTemplatesCommand;
use CraftCms\Commerce\Console\Commands\Gateways\GatewaysListCommand;
use CraftCms\Commerce\Console\Commands\Gateways\GatewaysWebhookUrlCommand;
use CraftCms\Commerce\Console\Commands\PricingCatalog\PricingCatalogGenerateCommand;
use CraftCms\Commerce\Console\Commands\Resave\ResaveCartsCommand;
use CraftCms\Commerce\Console\Commands\Resave\ResaveOrdersCommand;
use CraftCms\Commerce\Console\Commands\Resave\ResaveProductsCommand;
use CraftCms\Commerce\Console\Commands\Resave\ResaveVariantsCommand;
use CraftCms\Commerce\Console\Commands\ResetData\ResetDataCommand;
use CraftCms\Commerce\Console\Commands\TransferCustomerData\TransferCustomerDataCommand;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings;
use CraftCms\Commerce\Customer\Fields\IsPrimaryBillingField;
use CraftCms\Commerce\Customer\Fields\IsPrimaryShippingField;
use CraftCms\Commerce\Customer\Fields\PrimaryBillingAddressIdField;
use CraftCms\Commerce\Customer\Fields\PrimaryShippingAddressIdField;
use CraftCms\Commerce\Customer\Models\Customer as CustomerRecord;
use CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal;
use CraftCms\Commerce\Dashboard\Widgets\NewCustomers;
use CraftCms\Commerce\Dashboard\Widgets\Orders as OrdersWidget;
use CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers;
use CraftCms\Commerce\Dashboard\Widgets\TopCustomers;
use CraftCms\Commerce\Dashboard\Widgets\TopProducts;
use CraftCms\Commerce\Dashboard\Widgets\TopProductTypes;
use CraftCms\Commerce\Dashboard\Widgets\TopPurchasables;
use CraftCms\Commerce\Dashboard\Widgets\TotalOrders;
use CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry;
use CraftCms\Commerce\Dashboard\Widgets\TotalRevenue;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\EmailEvent;
use CraftCms\Commerce\Gql\Handlers\HasProduct;
use CraftCms\Commerce\Gql\Handlers\HasVariant;
use CraftCms\Commerce\Gql\Handlers\RelatedProducts;
use CraftCms\Commerce\Gql\Handlers\RelatedVariants;
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use CraftCms\Commerce\Gql\Queries\Product as ProductQuery;
use CraftCms\Commerce\Gql\Queries\Variant as VariantQuery;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Http\Controllers\Users\UsersController;
use CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\CartRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\PaymentSources;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Plugin\Concerns\HasPermissions;
use CraftCms\Commerce\Plugin\Concerns\HasServices;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAllowedQtyField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableAvailableForPurchaseField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableDimensionsField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableFreeShippingField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePriceField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasablePromotableField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableSkuField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableStockField;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableWeightField;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Store\StoreSettings;
use CraftCms\Commerce\Support\ObjectState;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField;
use CraftCms\Commerce\Transfer\Transfers;
use CraftCms\Commerce\Twig\Extension as CommerceTwigExtension;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class Plugin extends BasePlugin
{
    use HasPermissions;
    use HasServices;

    public const string HANDLE = 'commerce';

    protected array $elementTypes = [
        Product::class,
        Variant::class,
        Order::class,
        Donation::class,
        Transfer::class,
    ];

    protected array $fieldTypes = [
        ProductsField::class,
        VariantsField::class,
        IsPrimaryBillingField::class,
        IsPrimaryShippingField::class,
        PrimaryBillingAddressIdField::class,
        PrimaryShippingAddressIdField::class,
    ];

    protected array $widgets = [
        AverageOrderTotal::class,
        NewCustomers::class,
        OrdersWidget::class,
        RepeatCustomers::class,
        TotalOrders::class,
        TotalOrdersByCountry::class,
        TopCustomers::class,
        TopProducts::class,
        TopProductTypes::class,
        TopPurchasables::class,
        TotalRevenue::class,
    ];

    protected array $linkTypes = [
        ProductLinkType::class,
    ];

    protected array $gqlTypes = [
        ProductInterface::class,
        VariantInterface::class,
    ];

    protected array $gqlQueries = [
        ProductQuery::class,
        VariantQuery::class,
    ];

    protected array $commands = [
        ResaveProductsCommand::class,
        ResaveVariantsCommand::class,
        ResaveOrdersCommand::class,
        ResaveCartsCommand::class,
        ExampleTemplatesCommand::class,
        GatewaysListCommand::class,
        GatewaysWebhookUrlCommand::class,
        PricingCatalogGenerateCommand::class,
        ResetDataCommand::class,
        TransferCustomerDataCommand::class,
    ];

    public bool $hasCpSection = true;

    public function boot(): void
    {
        // Reconcile our type registries against any legacy `Event::on(...)` listeners for the
        // deprecated EVENT_REGISTER_* constants, once every plugin has finished registering its listeners.
        $this->app->booted(function() {
            if (!Cms::isInstalled(strict: true)) {
                return;
            }

            LegacyOrderAdjustments::finalizeRegistrationEvents();
            LegacyGateways::finalizeRegistrationEvents();
            LegacyPurchasables::finalizeRegistrationEvents();
        });

        $arguments = app(GqlArguments::class);
        $arguments->register('hasProduct', HasProduct::class);
        $arguments->register('hasVariant', HasVariant::class);
        $arguments->register('relatedToProducts', RelatedProducts::class);
        $arguments->register('relatedToVariants', RelatedVariants::class);

        $this->registerBehaviorMacros();
        $this->registerVariableMacros();

        Twig::registerExtension(new CommerceTwigExtension());

        if ($this->isInstalled) {
            $this->registerCraftEventListeners();
            $this->registerProjectConfigEventListeners();

            if (request()->isCpRequest()) {
                $this->registerCKEditorLinkOptions();
            }
        }
    }

    /**
     * Registers `craft.commerce`/`craft.orders`/`craft.products`/`craft.variants` Twig variable
     * macros, replacing the legacy `craft\commerce\web\twig\CraftVariableBehavior` attached to
     * `craft\web\twig\variables\CraftVariable` in `src-yii2/Plugin.php` (now deleted — that
     * behavior never reached the live `craft` Twig global under Craft 6 anyway).
     */
    private function registerVariableMacros(): void
    {
        $plugin = $this;
        CraftVariable::macro('commerce', fn() => $plugin);

        CraftVariable::macro('orders', function(array $criteria = []) {
            $query = Order::find();
            Typecast::configure($query, $criteria);

            return $query;
        });

        CraftVariable::macro('products', function(array $criteria = []) {
            $query = Product::find();
            Typecast::configure($query, $criteria);

            return $query;
        });

        CraftVariable::macro('variants', function(array $criteria = []) {
            $query = Variant::find();
            Typecast::configure($query, $criteria);

            return $query;
        });
    }

    /**
     * Reachable in Twig as `craft.commerce.getDonation()` (`craft.commerce` resolves to this
     * plugin instance via the `commerce` macro above), matching the legacy
     * `craft\commerce\plugin\Variables::getDonation()` trait method that used to live directly
     * on the legacy Plugin class for the same reason.
     */
    public function getDonation(): ?Donation
    {
        return Donation::find()->status(null)->one();
    }

    /**
     * Replaces the legacy Yii2 `StoreBehavior`/`CustomerBehavior`/`CustomerAddressBehavior` classes,
     * which no longer attach to anything — `Site`/`User`/`Address` extend the new
     * `CraftCms\Cms\Component\Component`, not `yii\base\Component`, so `attachBehavior()` doesn't exist
     * on them at all. `Macroable` (already `use`d by `Component`) is the replacement mechanism; its
     * `MacroableMagicMethods` concern makes registered macros transparently reachable via method-call
     * syntax (`$site->getStore()`), PHP magic-property syntax (`$site->store`), and Twig dot-notation
     * (`{{ site.store }}`) alike — verified empirically via `php artisan tinker` this session.
     */
    private function registerBehaviorMacros(): void
    {
        Site::macro('getStore', function(): ?Store {
            /** @var Site $this */
            return app(Stores::class)->getStoreBySiteId($this->id);
        });

        $this->registerCustomerMacros();
        $this->registerCustomerAddressMacros();
    }

    /**
     * Replaces `craft\commerce\behaviors\CustomerBehavior`, attached to `User` in Commerce 5.
     */
    private function registerCustomerMacros(): void
    {
        User::macro('getPrimaryBillingAddressId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryBillingAddressId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();
                ObjectState::set($this, 'primaryBillingAddressId', $customer?->primaryBillingAddressId);
            }

            return ObjectState::get($this, 'primaryBillingAddressId');
        });

        User::macro('setPrimaryBillingAddressId', function(?int $primaryBillingAddressId): void {
            ObjectState::set($this, 'primaryBillingAddressId', $primaryBillingAddressId);
        });

        User::macro('getPrimaryBillingAddress', function(): ?Address {
            /** @var User $this */
            /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is another macro registered above, not visible to static analysis) */
            return $this->getAddresses()->firstWhere('id', $this->getPrimaryBillingAddressId());
        });

        User::macro('getPrimaryShippingAddressId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryShippingAddressId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();
                ObjectState::set($this, 'primaryShippingAddressId', $customer?->primaryShippingAddressId);
            }

            return ObjectState::get($this, 'primaryShippingAddressId');
        });

        User::macro('setPrimaryShippingAddressId', function(?int $primaryShippingAddressId): void {
            ObjectState::set($this, 'primaryShippingAddressId', $primaryShippingAddressId);
        });

        User::macro('getPrimaryShippingAddress', function(): ?Address {
            /** @var User $this */
            /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is another macro registered above, not visible to static analysis) */
            return $this->getAddresses()->firstWhere('id', $this->getPrimaryShippingAddressId());
        });

        User::macro('setPrimaryPaymentSourceId', function(?int $paymentSourceId): void {
            ObjectState::set($this, 'primaryPaymentSourceId', $paymentSourceId);
        });

        User::macro('getPrimaryPaymentSourceId', function(): ?int {
            /** @var User $this */
            if (!ObjectState::has($this, 'primaryPaymentSourceId')) {
                $customer = CustomerRecord::where('customerId', $this->id)->first();

                if (!$customer) {
                    return null;
                }

                if ($customer->primaryPaymentSourceId) {
                    ObjectState::set($this, 'primaryPaymentSourceId', $customer->primaryPaymentSourceId);
                } else {
                    /** @phpstan-ignore-next-line method.notFound (getPrimaryPaymentSource() is another macro registered below, not visible to static analysis) */
                    $paymentSource = $this->getPrimaryPaymentSource();
                    ObjectState::set($this, 'primaryPaymentSourceId', $paymentSource?->id);
                }
            }

            return ObjectState::get($this, 'primaryPaymentSourceId');
        });

        User::macro('getPrimaryPaymentSource', function(): ?PaymentSource {
            /** @var User $this */
            $paymentSources = app(PaymentSources::class)->getAllPaymentSourcesByCustomerId(customerId: $this->id);

            if ($paymentSources->isEmpty()) {
                return null;
            }

            $primaryId = ObjectState::get($this, 'primaryPaymentSourceId');

            if (!$primaryId) {
                return $paymentSources->first();
            }

            return $paymentSources->firstWhere('id', $primaryId);
        });

        User::macro('getActiveCarts', function(): array {
            /** @var User $this */
            $edge = app(Carts::class)->getActiveCartEdgeDuration();

            return Order::find()
                ->customer($this)
                ->isCompleted(false)
                ->where('elements.dateUpdated', '>=', $edge)
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('elements.dateUpdated', 'desc')
                ->all();
        });

        User::macro('getInactiveCarts', function(): array {
            /** @var User $this */
            $edge = app(Carts::class)->getActiveCartEdgeDuration();

            return Order::find()
                ->customer($this)
                ->isCompleted(false)
                ->where('elements.dateUpdated', '<', $edge)
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('elements.dateUpdated', 'asc')
                ->all();
        });

        User::macro('getOrders', function(): array {
            /** @var User $this */
            return Order::find()
                ->customer($this)
                ->isCompleted()
                ->withAll()
                /** @phpstan-ignore-next-line arguments.count (ElementQuery's @method static orderBy($column) docblock tag conflicts with its own real 2-param method signature) */
                ->orderBy('dateOrdered', 'desc')
                ->all();
        });
    }

    /**
     * Replaces `craft\commerce\behaviors\CustomerAddressBehavior`, attached to `Address` in Commerce 5.
     */
    private function registerCustomerAddressMacros(): void
    {
        Address::macro('getIsPrimaryBilling', function(): bool {
            /** @var Address $this */
            if (!ObjectState::has($this, 'isPrimaryBilling')) {
                $owner = $this->getPrimaryOwner();
                /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is a macro registered in registerCustomerMacros(), not visible to static analysis) */
                $value = $this->id && $owner instanceof User && $this->id === $owner->getPrimaryBillingAddressId();
                ObjectState::set($this, 'isPrimaryBilling', $value);
            }

            return ObjectState::get($this, 'isPrimaryBilling');
        });

        Address::macro('setIsPrimaryBilling', function(bool|string $value): void {
            ObjectState::set($this, 'isPrimaryBilling', (bool) $value);
        });

        Address::macro('hasIsPrimaryBillingBeenSet', function(): bool {
            /** @var Address $this */
            return ObjectState::has($this, 'isPrimaryBilling');
        });

        Address::macro('getIsPrimaryShipping', function(): bool {
            /** @var Address $this */
            if (!ObjectState::has($this, 'isPrimaryShipping')) {
                $owner = $this->getPrimaryOwner();
                /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is a macro registered in registerCustomerMacros(), not visible to static analysis) */
                $value = $this->id && $owner instanceof User && $this->id === $owner->getPrimaryShippingAddressId();
                ObjectState::set($this, 'isPrimaryShipping', $value);
            }

            return ObjectState::get($this, 'isPrimaryShipping');
        });

        Address::macro('setIsPrimaryShipping', function(bool|string $value): void {
            ObjectState::set($this, 'isPrimaryShipping', (bool) $value);
        });

        Address::macro('hasIsPrimaryShippingBeenSet', function(): bool {
            /** @var Address $this */
            return ObjectState::has($this, 'isPrimaryShipping');
        });
    }

    private function registerCraftEventListeners(): void
    {
        Event::listen(Login::class, static fn() => app(Customers::class)->loginHandler());
        Event::listen(Logout::class, static fn() => app(Carts::class)->forgetCart());

        Event::listen(ElementSaved::class, static function(ElementSaved $event) {
            if ($event->element instanceof User) {
                app(Carts::class)->afterSaveUserHandler($event);
                app(CatalogPricingRules::class)->afterSaveUserHandler($event);
                app(Customers::class)->afterSaveUserHandler($event);
            }

            if ($event->element instanceof Address) {
                app(Orders::class)->afterSaveAddressHandler($event);
                app(Customers::class)->afterSaveAddressHandler($event);
            }
        });

        Event::listen(UserAssignedToGroups::class, static fn(UserAssignedToGroups $event) => app(CatalogPricingRules::class)->afterSaveUserHandler($event));

        Event::listen(SiteSaved::class, static function(SiteSaved $event) {
            app(ProductTypes::class)->afterSaveSiteHandler($event);
            app(Products::class)->afterSaveSiteHandler($event);
            app(Stores::class)->afterSaveCraftSiteHandler($event);
        });

        Event::listen(SiteDeleted::class, static fn(SiteDeleted $event) => app(Stores::class)->afterDeleteCraftSiteHandler($event));

        Event::listen(DefineDeletionBlockers::class, static function(DefineDeletionBlockers $event) {
            if ($event->elementType === User::class) {
                app(Orders::class)->beforeDeleteUserHandler($event);
            }
        });

        Event::listen(ElementAuthorizing::class, static function(ElementAuthorizing $event) {
            match ($event->ability) {
                'view' => app(StoreSettings::class)->authorizeStoreLocationView($event),
                'save', 'createDrafts' => app(StoreSettings::class)->authorizeStoreLocationEdit($event),
                default => null,
            };

            match ($event->ability) {
                'view' => app(InventoryLocations::class)->authorizeInventoryLocationAddressView($event),
                'save', 'createDrafts' => app(InventoryLocations::class)->authorizeInventoryLocationAddressEdit($event),
                default => null,
            };
        });
    }

    /**
     * Registers Commerce's project config event listeners.
     */
    private function registerProjectConfigEventListeners(): void
    {
        $projectConfig = app(ProjectConfig::class);

        $projectConfig->onAdd(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleChangedGateway(...))
            ->onUpdate(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleChangedGateway(...))
            ->onRemove(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleArchivedGateway(...));

        $projectConfig->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleChangedProductType(...))
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleChangedProductType(...))
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleDeletedProductType(...));

        Event::listen(SiteDeleted::class, static function(SiteDeleted $event) {
            if (!app(ProjectConfig::class)->isApplyingExternalChanges) {
                app(ProductTypes::class)->pruneDeletedSite($event);
            }
        });

        $projectConfig->onAdd(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleChangedFieldLayout(...))
            ->onUpdate(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleChangedFieldLayout(...))
            ->onRemove(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleDeletedFieldLayout(...));

        $projectConfig->onAdd(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleChangedFieldLayout(...))
            ->onUpdate(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleChangedFieldLayout(...))
            ->onRemove(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleDeletedFieldLayout(...));

        $projectConfig->onAdd(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleChangedOrderStatus(...))
            ->onUpdate(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleChangedOrderStatus(...))
            ->onRemove(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleDeletedOrderStatus(...));

        // Emails still fires this via the legacy craft\commerce\services\Emails shim's
        // hasEventHandlers()/trigger() (see the TODO in Emails::handleDeletedEmail()) — there's no
        // Laravel event to listen to yet, so this stays a legacy Event::on() registration.
        YiiEvent::on(LegacyEmails::class, LegacyEmails::EVENT_AFTER_DELETE_EMAIL, static function(EmailEvent $event) {
            if (!app(ProjectConfig::class)->isApplyingExternalChanges) {
                app(OrderStatuses::class)->pruneDeletedEmail($event);
            }
        });

        $projectConfig->onAdd(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleChangedLineItemStatus(...))
            ->onUpdate(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleChangedLineItemStatus(...))
            ->onRemove(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleArchivedLineItemStatus(...));

        $projectConfig->onAdd(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleChangedEmail(...))
            ->onUpdate(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleChangedEmail(...))
            ->onRemove(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleDeletedEmail(...));

        $projectConfig->onAdd(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleChangedStore(...))
            ->onUpdate(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleChangedStore(...))
            ->onRemove(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleDeletedStore(...));

        $projectConfig->onAdd(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleChangedSiteStore(...))
            ->onUpdate(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleChangedSiteStore(...))
            ->onRemove(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleDeletedSiteStore(...));

        $projectConfig->onAdd(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleChangedPdf(...))
            ->onUpdate(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleChangedPdf(...))
            ->onRemove(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleDeletedPdf(...));

        Event::listen(ProjectConfigRebuilt::class, static function(ProjectConfigRebuilt $event) {
            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    /**
     * Registers a product/variant link option for the CKEditor field's rich text link chooser.
     *
     * CKEditor itself is still Yii2-based (not yet ported to Laravel), so it only exposes this via
     * the legacy `EVENT_DEFINE_LINK_OPTIONS` Yii event — there's no Laravel event to listen to here.
     * Replaces the Redactor equivalent that was dropped entirely (Redactor is not supported under
     * Craft 6). TODO: After CKeditor is on 6.x port this
     */
    private function registerCKEditorLinkOptions(): void
    {
        if (!class_exists(CKEditorField::class)) {
            return;
        }

        $ckEditorPlugin = Plugins::getPlugin('ckeditor');
        if (!$ckEditorPlugin || version_compare($ckEditorPlugin->version, '3.0', '<')) {
            return;
        }

        /** @phpstan-ignore-next-line argument.type, class.notFound (craft\ckeditor\Field/DefineLinkOptionsEvent belong to the optional, third-party craftcms/ckeditor plugin, not a commerce dependency — unresolvable to static analysis when it isn't installed) */
        YiiEvent::on(CKEditorField::class, CKEditorField::EVENT_DEFINE_LINK_OPTIONS, static function(DefineLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $sites = Sites::getAllSites();

            foreach (app(ProductTypes::class)->getAllProductTypes() as $productType) {
                foreach ($sites as $site) {
                    $productTypeSettings = $productType->getSiteSettings();
                    if (isset($productTypeSettings[$site->id]) && $productTypeSettings[$site->id]->hasUrls) {
                        $productSources[] = 'productType:' . $productType->uid;
                    }
                }
            }

            $productSources = array_unique($productSources);

            if ($productSources) {
                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a product', category: 'commerce'),
                    'elementType' => Product::class,
                    'refHandle' => Product::refHandle(),
                    'sources' => $productSources,
                ];

                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a variant', category: 'commerce'),
                    'elementType' => Variant::class,
                    'refHandle' => Variant::refHandle(),
                    'sources' => $productSources,
                ];
            }
        });
    }

    public function register(): void
    {
        // Gateway webhooks and off-site payment returns can't provide a CSRF token
        $routes = app(Routes::class);
        PreventRequestForgery::except([
            'commerce/webhooks/process-webhook/gateway/*',
            $routes->actionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
            $routes->cpActionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
            $routes->actionTriggerUriPrefix() . '/commerce/payments/complete-payment',
            $routes->cpActionTriggerUriPrefix() . '/commerce/payments/complete-payment',
        ]);

        RateLimiter::for(CartRateLimiter::NAME, fn(Request $request) => app(CartRateLimiter::class)->limit($request));
        RateLimiter::for(CartChallengeRateLimiter::NAME, fn(Request $request) => app(CartChallengeRateLimiter::class)->limit($request));
        RateLimiter::for(PdfChallengeRateLimiter::NAME, fn(Request $request) => app(PdfChallengeRateLimiter::class)->limit($request));

        // Add a "Commerce" screen to the Edit User screen for users who can access Commerce
        Event::listen(EditUserScreensResolving::class, function(EditUserScreensResolving $event) {
            if (currentUser()?->can('accessPlugin-commerce')) {
                $event->screens[UsersController::SCREEN_COMMERCE] = ['label' => t('Commerce', category: 'commerce')];
            }
        });

        Event::listen(GqlSchemaComponentsResolving::class, function(GqlSchemaComponentsResolving $event) {
            $productTypes = app(ProductTypes::class)->getAllProductTypes();

            if (empty($productTypes)) {
                return;
            }

            $label = t('Products', category: 'commerce');
            $productPermissions = [];

            foreach ($productTypes as $productType) {
                $suffix = 'productTypes.' . $productType->uid;
                $productPermissions[$suffix . ':read'] = [
                    'label' => t('View product type - {productType}', ['productType' => t($productType->name, category: 'site')], category: 'commerce'),
                ];
            }

            $event->queries[$label] = $productPermissions;
        });

        Event::listen(GqlEagerLoadableFieldsResolving::class, function(GqlEagerLoadableFieldsResolving $event) {
            $event->fieldList['variants'] = [ProductsField::class];
            $event->fieldList['product'] = [VariantsField::class];
        });

        Event::listen(RunningGarbageCollection::class, function(RunningGarbageCollection $event) {
            app(Carts::class)->purgeIncompleteCarts();

            DB::table(Table::VARIANTS)->whereNull('primaryOwnerId')->delete();

            foreach ([
                [Donation::class, Table::DONATIONS],
                [Order::class, Table::ORDERS],
                [Product::class, Table::PRODUCTS],
                [Variant::class, Table::VARIANTS],
                [Transfer::class, Table::TRANSFERS],
            ] as [$elementType, $table]) {
                app(DeletePartialElements::class, [
                    'garbageCollection' => $event->garbageCollection,
                    'elementType' => $elementType,
                    'table' => $table,
                ])();
            }
        });
    }

    /**
     * The CP nav's `craft-icon` component resolves `icon` to a published icon
     * *name* (e.g. `/vendor/craft/icons/solid/cart-shopping.svg`), not a
     * filesystem path, so the base `cpNavIconPath()` (which points at
     * `resources/icon-mask.svg`) never renders here. Use a published system
     * icon until plugin-contributed custom icons are supported.
     */
    #[\Override]
    protected function cpNavIconPath(): ?string
    {
        return 'cart-shopping';
    }

    #[\Override]
    public function getCpNavItem(): NavItem|array|null
    {
        $item = parent::getCpNavItem();

        if (!$item instanceof NavItem) {
            return $item;
        }

        $item->label(t('Commerce', category: 'commerce'));

        if (currentUser()?->can('commerce-manageOrders')) {
            $item->add(new NavItem()->label(t('Orders', category: 'commerce'))->url('commerce/orders'));
        }

        if (app(ProductTypes::class)->getViewableProductTypeIds(true)) {
            $item->add(new NavItem()->label(t('Products', category: 'commerce'))->url('commerce/products'));
        }

        if (currentUser()?->can('commerce-manageInventoryStockLevels')) {
            $item->add(new NavItem()->label(t('Inventory', category: 'commerce'))->url('commerce/inventory'));
        }

        if (currentUser()?->can('commerce-manageInventoryLocations')) {
            $item->add(new NavItem()->label(t('Inventory Locations', category: 'commerce'))->url('commerce/inventory-locations'));
        }

        $multipleLocations = app(InventoryLocations::class)->getAllInventoryLocations()->count() > 1;
        if ($multipleLocations && currentUser()?->can('commerce-manageInventoryTransfers')) {
            $item->add(new NavItem()->label(t('Inventory Transfers', category: 'commerce'))->url('commerce/inventory/transfers'));
        }

        if (currentUser()?->can('commerce-manageDonationSettings')) {
            $item->add(new NavItem()->label(t('Donations', category: 'commerce'))->url('commerce/donations'));
        }

        if (currentUser()?->can('commerce-manageStoreSettings')) {
            $item->add(new NavItem()->label(t('Store Management', category: 'commerce'))->url('commerce/store-management'));
        }

        if (currentUser()?->isAdmin()) {
            $item->add(new NavItem()
                ->label(t('Settings', category: 'app'))
                ->ariaLabel(t('Commerce Settings', category: 'commerce'))
                ->url('commerce/settings/general'));
        }

        return $item;
    }

    #[\Override]
    protected function getSystemMessages(): array
    {
        return [
            'commerce_pdf_download' => fn() => new SystemMessage([
                'key' => 'commerce_pdf_download',
                'heading' => t('Order PDF Download Link', category: 'commerce'),
                'subject' => t('Your Order PDF Download Link', category: 'commerce'),
                'body' => $this->defaultPdfDownloadMessage(),
            ]),
            'commerce_cart_recovery' => fn() => new SystemMessage([
                'key' => 'commerce_cart_recovery',
                'heading' => t('Cart Recovery Link', category: 'commerce'),
                'subject' => t('Your Cart Recovery Link', category: 'commerce'),
                'body' => $this->defaultCartRecoveryMessage(),
            ]),
        ];
    }

    #[\Override]
    protected function getCacheOptions(): array
    {
        return [
            'commerce-order-exports' => [
                'label' => t('Commerce order exports', category: 'commerce'),
                'action' => static function() {
                    File::cleanDirectory(app(Path::class)->runtime('commerce-order-exports'));
                },
            ],
        ];
    }

    #[\Override]
    protected function getNativeFields(): ?Closure
    {
        return function(FieldLayout $fieldLayout, array $fields): array {
            switch ($fieldLayout->type) {
                case Address::class:
                    $fields[] = UserAddressSettings::class;
                    break;
                case Product::class:
                    $fields[] = ProductTitleField::class;
                    $fields[] = VariantsLayoutElement::class;
                    break;
                case Transfer::class:
                    $fields[] = TransferManagementField::class;
                    break;
                case Variant::class:
                    $fields[] = VariantTitleField::class;
                    $fields[] = PurchasableSkuField::class;
                    $fields[] = PurchasablePriceField::class;
                    $fields[] = PurchasableStockField::class;
                    $fields[] = PurchasableAvailableForPurchaseField::class;
                    $fields[] = PurchasableAllowedQtyField::class;
                    $fields[] = PurchasableFreeShippingField::class;
                    $fields[] = PurchasablePromotableField::class;
                    $fields[] = PurchasableDimensionsField::class;
                    $fields[] = PurchasableWeightField::class;
                    break;
            }

            return $fields;
        };
    }

    /**
     * Returns the default message body for the PDF download email.
     */
    private function defaultPdfDownloadMessage(): string
    {
        return "Hello,\n\n" .
            "You requested a PDF download for your order. Click the link below to download your PDF:\n\n" .
            "[Download PDF]({{ link }})\n\n" .
            "**Please note:** This link will expire for security purposes.\n\n" .
            "Thank you!";
    }

    /**
     * Returns the default message body for the cart recovery email.
     */
    private function defaultCartRecoveryMessage(): string
    {
        return "Hello,\n\n" .
            "You requested a link to recover your shopping cart. Click the link below to continue shopping:\n\n" .
            "[Recover My Cart]({{ link }})\n\n" .
            "**Please note:** This link will expire for security purposes.\n\n" .
            "Thank you!";
    }
}
