<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\assets\fieldsettings\FieldSettingsAsset;
use craft\web\Controller;
use yii\web\Response;

/**
 * Inventory Locations controller
 */
class InventoryLocationsController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * Inventory Locations index
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $variables = [
            'inventoryLocations' => $inventoryLocations,
            'selectedItem' => 'inventory',
        ];

        $screen = $this->asCpScreen()
            ->title(Craft::t('commerce', 'Inventory Locations'))
            ->addCrumb(Craft::t('app', 'Inventory'), 'commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->pageSidebarTemplate('commerce/inventory/_sidebar', $variables)
            ->contentTemplate('commerce/inventory/locations/_index', $variables);

        $locationCount = count($inventoryLocations);
        $showNewButton = false;
        $userCanCreate = ($currentUser && $currentUser->can('commerce-createLocations'));

        // If they have no locations they can have at least 1 when on lite
        if ($locationCount < Plugin::EDITION_LITE_STORE_LIMIT) {
            $showNewButton = true;
        }

        if (!$showNewButton && Plugin::getInstance()->is(Plugin::EDITION_PRO, '>=') && $locationCount < Plugin::EDITION_PRO_STORE_LIMIT) {
            $showNewButton = true;
        }

        if ($userCanCreate && $showNewButton) {
            $button = Html::a(
                Craft::t('commerce', 'New location'),
                'commerce/inventory/locations/new',
                [
                    'class' => 'btn submit add icon',
                ]);
            $screen->additionalButtonsHtml($button);
        }

        return $screen;
    }

    /**
     * @param int|null $inventoryLocationId
     * @param InventoryLocation|null $inventoryLocation
     * @return Response
     * @throws \yii\base\InvalidConfigException
     */
    public function actionEdit(?int $inventoryLocationId = null, ?InventoryLocation $inventoryLocation = null): Response
    {
        if ($inventoryLocationId !== null) {
            if ($inventoryLocation === null) {
                $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);

                if (!$inventoryLocation) {
                    throw new NotFoundHttpException('Inventory location not found');
                }
            }

            $title = trim($inventoryLocation->name) ?: Craft::t('app', 'Edit Inventory Location');
        } else {
            if ($inventoryLocation === null) {
                $inventoryLocation = new InventoryLocation();

                $title = Craft::t('app', 'Create a new inventory location');
            }
        }

            $address = $inventoryLocation->getAddress();

            if ($inventoryLocation->id && !$address->id) {
                if (Craft::$app->getElements()->saveElement($address, false)) {
                    $inventoryLocation->setAddress($address);
                    Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($inventoryLocation, false);
                } else {
                    throw new \Exception('Could not save store location address');
                }
            }

            $addressCardId = 'inventory-location-address';
            $locationFieldHtml = Html::tag('div',Cp::elementCardHtml($address, [
                'context' => 'field',
                'inputName' => 'addressId',
                'showActionMenu' => true,
            ]),['id'=> $addressCardId]);

            $variables = [
                'inventoryLocationId' => $inventoryLocationId,
                'inventoryLocation' => $inventoryLocation,
                'typeName' => Craft::t('commerce', 'Inventory Location'),
                'lowerTypeName' => Craft::t('commerce', 'inventory location'),
                'locationFieldHtml' => $locationFieldHtml
            ];

            return $this->asCpScreen()
                ->title($title)
                ->addCrumb(Craft::t('app', 'Inventory'), 'commerce/inventory')
                ->addCrumb(Craft::t('app', 'Locations'), 'commerce/inventory/locations')
                ->action('commerce/inventory-locations/save')
                ->redirectUrl('commerce/inventory/locations')
                ->selectedSubnavItem('inventory')
                ->contentTemplate('commerce/inventory/locations/_edit', $variables)
                ->metaSidebarTemplate('commerce/inventory/locations/_sidebar', $variables)
                ->prepareScreen(function() use ($addressCardId){
                    $view = Craft::$app->getView();
                    $view->registerJsWithVars(fn($id, $elementType) => <<<JS
let storeLocation = document.querySelector('#' + $id);
storeLocation.addEventListener('dblclick', function() {
  const slideout = Craft.createElementEditor(
    $elementType,
    storeLocation.querySelector('.element.card')
  );
});
JS, [$addressCardId, 'craft\elements\Address']);
                });

    }

        /**
         * @return Response
         * @throws \yii\base\ErrorException
         * @throws \yii\base\Exception
         * @throws \yii\base\InvalidConfigException
         * @throws \yii\base\NotSupportedException
         * @throws \yii\web\MethodNotAllowedHttpException
         * @throws \yii\web\ServerErrorHttpException
         */
        public
        function actionSave(): ?Response
        {
            $this->requirePostRequest();

            // find the inventory location or make a new one
            $inventoryLocationId = Craft::$app->getRequest()->getBodyParam('inventoryLocationId');
            $inventoryLocation = null;

            if ($inventoryLocationId) {
                $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
            }

            if (!$inventoryLocation) {
                $inventoryLocation = new InventoryLocation();
            }

            $inventoryLocation->name = Craft::$app->getRequest()->getBodyParam('name');
            $inventoryLocation->handle = Craft::$app->getRequest()->getBodyParam('handle');
            $inventoryLocation->addressId = Craft::$app->getRequest()->getBodyParam('addressId');

            if (!Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($inventoryLocation)) {
                return $this->asModelFailure(
                    model: $inventoryLocation,
                    message: Craft::t('commerce', 'Couldn’t save inventory location.'),
                    modelName: 'inventoryLocation'
                );
            }

            return $this->asModelSuccess(
                model: $inventoryLocation,
                message: Craft::t('commerce', 'Inventory location saved.'),
                modelName: 'inventoryLocation'
            );
        }

        /**
         * @return Response
         * @throws \Throwable
         * @throws \yii\base\InvalidConfigException
         * @throws \yii\db\Exception
         * @throws \yii\web\BadRequestHttpException
         * @throws \yii\web\MethodNotAllowedHttpException
         */
        public
        function actionDelete(): Response
        {
            $this->requirePostRequest();
            $this->requireAcceptsJson();

            $inventoryLocationId = Craft::$app->getRequest()->getRequiredBodyParam('id');

            if (Plugin::getInstance()->getInventoryLocations()->deleteInventoryLocationById($inventoryLocationId)) {
                return $this->asJson(['success' => true]);
            };

            return $this->asJson(['success' => false]);
        }
    }
