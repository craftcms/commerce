<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use Closure;
use craft\commerce\services\Gateways as LegacyGateways;
use craft\commerce\services\OrderAdjustments as LegacyOrderAdjustments;
use craft\commerce\services\Purchasables as LegacyPurchasables;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Queries\Events\ElementsHydrated;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlEagerLoadableFieldsResolving;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlArguments;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Twig;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Path;
use CraftCms\Cms\SystemMessage\Models\SystemMessage;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\FieldLayoutElements\ProductTitleField;
use CraftCms\Commerce\Catalog\FieldLayoutElements\VariantsField as VariantsLayoutElement;
use CraftCms\Commerce\Catalog\FieldLayoutElements\VariantTitleField;
use CraftCms\Commerce\Catalog\Fields\Products as ProductsField;
use CraftCms\Commerce\Catalog\Fields\Variants as VariantsField;
use CraftCms\Commerce\Catalog\LinkTypes\ProductLinkType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
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
use CraftCms\Commerce\Customer\FieldLayoutElements\UserAddressSettings;
use CraftCms\Commerce\Customer\Fields\IsPrimaryBillingField;
use CraftCms\Commerce\Customer\Fields\IsPrimaryShippingField;
use CraftCms\Commerce\Customer\Fields\PrimaryBillingAddressIdField;
use CraftCms\Commerce\Customer\Fields\PrimaryShippingAddressIdField;
use CraftCms\Commerce\Customer\Listeners\ElementsHydratedListener;
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
use CraftCms\Commerce\Gql\Handlers\HasProduct;
use CraftCms\Commerce\Gql\Handlers\HasVariant;
use CraftCms\Commerce\Gql\Handlers\RelatedProducts;
use CraftCms\Commerce\Gql\Handlers\RelatedVariants;
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use CraftCms\Commerce\Gql\Queries\Product as ProductQuery;
use CraftCms\Commerce\Gql\Queries\Variant as VariantQuery;
use CraftCms\Commerce\Http\Controllers\Users\UsersController;
use CraftCms\Commerce\Http\Middleware\PoweredByHeader;
use CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\CartRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Plugin\Concerns\HasCommerceEditions;
use CraftCms\Commerce\Plugin\Concerns\HasCommerceEventListeners;
use CraftCms\Commerce\Plugin\Concerns\HasCommerceMacros;
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
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField;
use CraftCms\Commerce\Twig\Extension as CommerceTwigExtension;
use GraphQL\Type\Definition\Type;
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
    use HasCommerceEditions;
    use HasCommerceMacros;
    use HasCommerceEventListeners;

    public const string HANDLE = 'commerce';

    public string $schemaVersion = '5.7.0.0';

    public string $minVersionRequired = '3.4.11';

    public bool $hasCpSettings = true;

    public bool $hasReadOnlyCpSettings = true;

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

    protected array $events = [
        ElementsHydrated::class => ElementsHydratedListener::class,
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
        $this->registerGqlRelatedToArguments();
        $this->registerForeignKeysRestore();

        Twig::registerExtension(new CommerceTwigExtension());

        $this->app['router']->pushMiddlewareToGroup('craft', PoweredByHeader::class);

        if ($this->isInstalled) {
            $this->registerCraftEventListeners();
            $this->registerProjectConfigEventListeners();

            if (request()->isCpRequest()) {
                $this->registerCKEditorLinkOptions();
                $this->registerLegacyCpRoutes();
            }
        }
    }

    #[\Override]
    public function getSettingsResponse(): mixed
    {
        return redirect('commerce/settings/general');
    }

    #[\Override]
    public function getReadOnlySettingsResponse(): mixed
    {
        return redirect('commerce/settings/general');
    }

    #[\Override]
    protected function createSettingsModel(): ?Settings
    {
        return new Settings();
    }

    /**
     * Narrows the return type from the base `?Validatable` to `?Settings`, since Commerce's
     * settings model is always a `Settings` instance (see `createSettingsModel()`).
     */
    #[\Override]
    public function getSettings(): ?Settings
    {
        /** @var ?Settings */
        return parent::getSettings();
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
