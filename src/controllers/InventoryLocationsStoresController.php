<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\Cp as CommerceCp;
use craft\commerce\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * Store Inventory Locations controller
 *
 * @since 5.0
 */
class InventoryLocationsStoresController extends BaseStoreManagementController
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * Inventory Locations index
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $store = Plugin::getInstance()->getStores()->getStoreByHandle(Craft::$app->getRequest()->getSegment(3));
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($store->id);
        $currentUser = Craft::$app->getUser()->getIdentity();


        $inventoryLocationsField = CommerceCp::inventoryLocationFieldHtml([
            'label' => Craft::t('commerce', 'Inventory Locations'),
            'instructions' => Craft::t('commerce', 'The inventory locations this store uses.'),
            'id' => 'inventoryLocations',
            'name' => 'inventoryLocations[]',
            'values' => $inventoryLocations,
            'create' =>  Plugin::getInstance()->is(Plugin::EDITION_PRO, '>=')
        ]);

        $variables = [
            'inventoryLocations' => $inventoryLocations,
            'inventoryLocationsField' => $inventoryLocationsField,
            'storeSettingsNav' => $this->getStoreSettingsNav(),
            'store' => $store,
        ];

        return $this->renderTemplate('commerce/store-management/inventory-locations/index', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $store = Plugin::getInstance()->getStores()->getStoreById(Craft::$app->getRequest()->getParam('storeId'));
        $inventoryLocations = Craft::$app->getRequest()->getParam('inventoryLocations');

        if (!$inventoryLocations) {
            return $this->asFailure(
                Craft::t('commerce', 'Missing a default inventory location.'),

            );
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        $success = Plugin::getInstance()->getInventoryLocations()->saveStoreInventoryLocations($store, $inventoryLocations);

        if ($success) {
            return $this->asSuccess(
                Craft::t('commerce', 'Inventory locations saved.')
            );
        } else {
            return $this->asFailure(
                Craft::t('commerce', 'Inventory locations not saved.')
            );
        }
    }
}
