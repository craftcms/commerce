<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use CraftCms\Cms\Auth\Events\ElementAuthorizing;
use CraftCms\Cms\Element\Events\DefineDeletionBlockers;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Queries\Events\ElementsHydrated;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlArgumentsResolving;
use CraftCms\Cms\Gql\Events\GqlEagerLoadableFieldsResolving;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Site\Events\SiteSaved;
use CraftCms\Cms\Support\Facades\Twig;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Cms\User\Events\UserAssignedToGroups;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
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
use CraftCms\Commerce\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\Commerce\Gql\Interfaces\Elements\Variant as VariantInterface;
use CraftCms\Commerce\Gql\Queries\Product as ProductQuery;
use CraftCms\Commerce\Gql\Queries\Variant as VariantQuery;
use CraftCms\Commerce\Http\Middleware\PoweredByHeader;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Plugin\Concerns\HasCacheOptions;
use CraftCms\Commerce\Plugin\Concerns\HasCommandPalette;
use CraftCms\Commerce\Plugin\Concerns\HasCommerceEditions;
use CraftCms\Commerce\Plugin\Concerns\HasCommerceMacros;
use CraftCms\Commerce\Plugin\Concerns\HasCpNavigation;
use CraftCms\Commerce\Plugin\Concerns\HasCpSettings;
use CraftCms\Commerce\Plugin\Concerns\HasGql;
use CraftCms\Commerce\Plugin\Concerns\HasHttpProtection;
use CraftCms\Commerce\Plugin\Concerns\HasLegacyBridges;
use CraftCms\Commerce\Plugin\Concerns\HasNativeFields;
use CraftCms\Commerce\Plugin\Concerns\HasPermissions;
use CraftCms\Commerce\Plugin\Concerns\HasProjectConfigListeners;
use CraftCms\Commerce\Plugin\Concerns\HasServices;
use CraftCms\Commerce\Plugin\Concerns\HasSystemMessages;
use CraftCms\Commerce\Plugin\Concerns\HasUi;
use CraftCms\Commerce\Plugin\Listeners\DefineDeletionBlockersListener;
use CraftCms\Commerce\Plugin\Listeners\EditUserScreensResolvingListener;
use CraftCms\Commerce\Plugin\Listeners\ElementAuthorizingListener;
use CraftCms\Commerce\Plugin\Listeners\ElementSavedListener;
use CraftCms\Commerce\Plugin\Listeners\GqlArgumentsResolvingListener;
use CraftCms\Commerce\Plugin\Listeners\GqlEagerLoadableFieldsResolvingListener;
use CraftCms\Commerce\Plugin\Listeners\GqlSchemaComponentsResolvingListener;
use CraftCms\Commerce\Plugin\Listeners\LoginListener;
use CraftCms\Commerce\Plugin\Listeners\LogoutListener;
use CraftCms\Commerce\Plugin\Listeners\RunningGarbageCollectionListener;
use CraftCms\Commerce\Plugin\Listeners\SiteDeletedListener;
use CraftCms\Commerce\Plugin\Listeners\SiteSavedListener;
use CraftCms\Commerce\Plugin\Listeners\UserAssignedToGroupsListener;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Fields\Products as ProductsField;
use CraftCms\Commerce\Product\Fields\Variants as VariantsField;
use CraftCms\Commerce\Product\LinkTypes\ProductLinkType;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Twig\Extension as CommerceTwigExtension;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class Plugin extends BasePlugin
{
    use HasPermissions;
    use HasServices;
    use HasCommerceEditions;
    use HasCommerceMacros;
    use HasProjectConfigListeners;
    use HasLegacyBridges;
    use HasCpNavigation;
    use HasSystemMessages;
    use HasNativeFields;
    use HasGql;
    use HasUi;
    use HasCpSettings;
    use HasHttpProtection;
    use HasCacheOptions;
    use HasCommandPalette;

    public const string HANDLE = 'commerce';

    public string $schemaVersion = '6.0.0.0';

    // TODO: keep in sync with the latest 5.x patch release as it advances, right up until 6.0 stable ships
    public string $minVersionRequired = '5.7.7';

    public bool $hasCpSettings = false;

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
        Login::class => LoginListener::class,
        Logout::class => LogoutListener::class,
        ElementSaved::class => ElementSavedListener::class,
        UserAssignedToGroups::class => UserAssignedToGroupsListener::class,
        SiteSaved::class => SiteSavedListener::class,
        SiteDeleted::class => SiteDeletedListener::class,
        DefineDeletionBlockers::class => DefineDeletionBlockersListener::class,
        ElementAuthorizing::class => ElementAuthorizingListener::class,
        EditUserScreensResolving::class => EditUserScreensResolvingListener::class,
        GqlArgumentsResolving::class => GqlArgumentsResolvingListener::class,
        GqlSchemaComponentsResolving::class => GqlSchemaComponentsResolvingListener::class,
        GqlEagerLoadableFieldsResolving::class => GqlEagerLoadableFieldsResolvingListener::class,
        RunningGarbageCollection::class => RunningGarbageCollectionListener::class,
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

    /** The Ui Node/Control Vue components Commerce contributes to the shared CP `Ui` system's
     *  component registry — see `resources/js/cp.ts` (registered via `window.Cp.$components`,
     *  since `@craftcms/ui`/`cms`'s own JS is a private, unpublished workspace package). Built by
     *  this repo's own root `package.json`/`vite.config.ts` (a pnpm workspace — the legacy Vue 2/
     *  webpack `commerceui` bundle lives in `./legacy`, a separate workspace member, since the two
     *  can't share a `vue` dependency). `'resources/js/cp.ts'` is the manifest entry key
     *  `vite.config.ts` builds under — the same entry-point path/convention `cms`'s own
     *  `resources/js/cp.ts` uses. */
    protected array $vite = [
        'resources/js/cp.ts',
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
        $this->finalizeLegacyRegistrationEvents();
        $this->registerGqlArguments();
        $this->registerUiComponents();
        $this->registerCpSettings();
        $this->registerCommandPalette();
        $this->registerPolicies();
        $this->registerBehaviorMacros();
        $this->registerVariableMacros();
        $this->registerForeignKeysRestore();
        $this->registerLegacyEventBridges();

        Twig::registerExtension(new CommerceTwigExtension());

        // TODO: remove once the remaining CP templates are ported to src/ (the conventional
        // {basePath}/templates root only covers what's already been moved there).
        app(TemplateRoots::class)->register(TemplateMode::Cp, $this->handle, dirname($this->getBasePath()) . '/src-yii2/templates');

        $this->app['router']->pushMiddlewareToGroup('craft', PoweredByHeader::class);

        if ($this->isInstalled) {
            $this->registerProjectConfigEventListeners();

            if (request()->isCpRequest()) {
                $this->registerCKEditorLinkOptions();
            }
        }
    }

    protected static function createSettings(): ?Settings
    {
        return new Settings();
    }

    #[\Override]
    public function getSettings(): ?Settings
    {
        /** @var ?Settings */
        return parent::getSettings();
    }

    public function register(): void
    {
        $this->registerCsrfExceptions();
        $this->registerRateLimiters();
    }
}
