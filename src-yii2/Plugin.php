<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce;

use Craft;
use craft\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\gql\interfaces\elements\Product as GqlProductInterface;
use craft\commerce\gql\interfaces\elements\Variant as GqlVariantInterface;
use craft\commerce\gql\queries\Product as GqlProductQueries;
use craft\commerce\gql\queries\Variant as GqlVariantQueries;
use CraftCms\Commerce\Gql\Types\Input\Criteria\ProductRelation;
use CraftCms\Commerce\Gql\Types\Input\Criteria\VariantRelation;
use craft\commerce\migrations\Install;
use craft\commerce\models\Settings;
use craft\commerce\plugin\Routes;
use CraftCms\Cms\Edition as CmsEdition;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\fixfks\controllers\RestoreController;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Gql;
use craft\web\Application;
use CraftCms\Commerce\Plugin as BasePlugin;
use Exception;
use yii\base\Event;

/**
 * @property array $cpNavItem the control panel navigation menu
 * @property Settings $settings
 * @property mixed $settingsResponse the settings page response
 * @method Settings getSettings()
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Plugin extends BasePlugin
{
    /**
     * Returns the editions for Craft Commerce
     *
     * @inheritDoc
     */


    /**
     * @inheritDoc
     */
    public string $schemaVersion = '5.7.0.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '3.4.11';

    /**
     * @inheritdoc
     */
    public CmsEdition $minCmsEdition = CmsEdition::Pro;

    /**
     * @inheritdoc
     */
    public bool $hasReadOnlyCpSettings = true;

    use Routes;

    public function boot(): void
    {
        parent::boot();

        $request = Craft::$app->getRequest();

        $this->_registerCraftEventListeners();
        $this->_registerForeignKeysRestore();
        $this->_registerGqlInterfaces();
        $this->_registerGqlQueries();
        $this->_registerRelatedToArguments();

        if ($request->getIsCpRequest()) {
            $this->_registerCpRoutes();
        } else {
            $this->_registerSiteRoutes();
        }

        Craft::setAlias('@commerceLib', Craft::getAlias('@craft/commerce/../lib'));
    }

    public function beforeInstall(): void
    {
        // Check version before installing
        if (version_compare(Craft::$app->getInfo()->version, '5.1.0', '<')) {
            throw new Exception('Craft Commerce 5 requires Craft CMS 5.1+ in order to run.');
        }

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80200) {
            Craft::error('Craft Commerce requires PHP 8.2.0+ in order to run.');
        }
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    public function getReadOnlySettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }


    /**
     * Register general event listeners
     */
    private function _registerCraftEventListeners(): void
    {
        // Guard against the case where the Plugin class is loaded during Craft installation due to a project config existing but commerce is not installed.
        // Also fixed in core but this is an extra guard: https://github.com/craftcms/cms/commit/369807d9b8da0ff0968e292591eee5f8924b57cc
        if (!$this->isInstalled) {
            return;
        }

        // Bulk customer attachment (primaryBillingAddressId / primaryShippingAddressId) is now
        // registered in src/Plugin.php, against CraftCms\Cms\Element\Queries\Events\ElementsHydrated
        // (the replacement for UserQuery::EVENT_AFTER_POPULATE_ELEMENTS).

        // Commerce screen on the Edit User screen is now registered in src/Plugin.php

        Event::on(Purchasable::class, Elements::EVENT_BEFORE_RESTORE_ELEMENT, [$this->getPurchasables(), 'beforeRestorePurchasableHandler']);
    }

    /**
     * Register for FK restore plugin
     */
    private function _registerForeignKeysRestore(): void
    {
        if (!class_exists(RestoreController::class)) {
            return;
        }

        Event::on(RestoreController::class, RestoreController::EVENT_AFTER_RESTORE_FKS, static function() {
            // Add default FKs
            new Install()->addForeignKeys();
        });
    }

    /**
     * Register the Gql interfaces
     */
    private function _registerGqlInterfaces(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, static function(RegisterGqlTypesEvent $event) {
            // Add my GraphQL types
            $types = $event->types;
            $types[] = GqlProductInterface::class;
            $types[] = GqlVariantInterface::class;
            $event->types = $types;
        });
    }

    /**
     * Register the Gql queries
     */
    private function _registerGqlQueries(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            // Add my GraphQL queries
            $event->queries = array_merge(
                $event->queries,
                GqlProductQueries::getQueries(),
                GqlVariantQueries::getQueries()
            );
        });
    }

    /**
     * Add relatedToProducts and relatedToVariants arguments to element queries.
     *
     * The handlers for these arguments themselves are registered via
     * CraftCms\Commerce\Plugin's boot() using the new GqlArguments registry.
     */
    private function _registerRelatedToArguments(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            $relatedToProductsArg = [
                'name' => 'relatedToProducts',
                'type' => \GraphQL\Type\Definition\Type::listOf(ProductRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a product list defined with this argument.',
            ];
            $relatedToVariantsArg = [
                'name' => 'relatedToVariants',
                'type' => \GraphQL\Type\Definition\Type::listOf(VariantRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a variant list defined with this argument.',
            ];

            // Add the arguments to all relevant queries
            foreach ($event->queries as $queryName => &$queryConfig) {
                if (isset($queryConfig['args']) && is_array($queryConfig['args'])) {
                    $queryConfig['args']['relatedToProducts'] = $relatedToProductsArg;
                    $queryConfig['args']['relatedToVariants'] = $relatedToVariantsArg;
                }
            }
        });
    }

}
