<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\inventory\DeactivateInventoryLocation;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\errors\DeprecationException;
use craft\fieldlayoutelements\addresses\AddressField;
use craft\helpers\Html;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Inventory Locations controller
 *
 * @since 5.0.0
 */
class InventoryLocationsController extends BaseStoreManagementController
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('commerce-manageInventoryLocations');
    }


    /**
     * Inventory Locations index
     *
     * @param string|null $storeHandle
     * @return Response
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws DeprecationException
     */
    public function actionIndex(string $storeHandle = null): Response
    {
        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $variables = [
            'store' => $store,
            'storeSettingsNav' => $this->getStoreSettingsNav(),
            'selectedItem' => 'inventory-locations',
        ];

        $screen = $this->asCpScreen()
            ->title(Craft::t('commerce', 'Store Management'))
            ->pageSidebarTemplate('commerce/_includes/_storeManagementNav', $variables)
            ->contentTemplate('commerce/store-management/inventory-locations/index', $variables);

        $locationCount = count($inventoryLocations);
        $showNewButton = false;
        $userCanCreate = ($currentUser && $currentUser->can('commerce-createLocations'));

        if ($locationCount < Plugin::EDITION_PRO_STORE_LIMIT) {
            $showNewButton = true;
        }

        if ($userCanCreate && $showNewButton) {
            $screen->additionalButtonsHtml(Html::a(
                Craft::t('commerce', 'New location'),
                'commerce/store-management/' . $store->handle . '/inventory-locations/new',
                [
                    'class' => 'btn submit add icon',
                ]
            ));
        }

        return $screen;
    }

    /**
     * @param int|null $inventoryLocationId
     * @param InventoryLocation|null $inventoryLocation
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionEdit(?string $storeHandle = null, ?int $inventoryLocationId = null, ?InventoryLocation $inventoryLocation = null): Response
    {
        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

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
            } else {
                $title = Craft::t('app', 'Create a new inventory location');
            }
        }

        $variables = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryLocation' => $inventoryLocation,
            'typeName' => Craft::t('commerce', 'Inventory Location'),
            'lowerTypeName' => Craft::t('commerce', 'inventory location'),
            'addressField' => new AddressField(),
            'countries' => Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language),
        ];

        return $this->asCpScreen()
            ->title($title)
            ->addCrumb(Craft::t('site', $store->getName()), 'commerce/store-management/' . $store->handle)
            ->addCrumb(Craft::t('app', 'Locations'), 'commerce/store-management/' . $store->handle . '/inventory-locations')
            ->action('commerce/inventory-locations/save')
            ->redirectUrl('commerce/store-management/' . $store->handle . '/inventory-locations')
            ->contentTemplate('commerce/store-management/inventory-locations/_edit', $variables)
            ->metaSidebarTemplate('commerce/store-management/inventory-locations/_sidebar', $variables);
    }

    /**
     * @return Response
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws MethodNotAllowedHttpException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
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

        // Pre-validate the inventory location so that we don't save the address if the rest isn't valid
        // This is to avoid orphaned addresses
        $isValid = $inventoryLocation->validate();

        if ($inventoryLocationAddress = Craft::$app->getRequest()->getBodyParam('inventoryLocationAddress')) {
            $inventoryLocationAddress['title'] = $inventoryLocation->name;
            if ($isValid) {
                $addressId = $inventoryLocationAddress['id'] ?: null;
                $address = $addressId ? Craft::$app->getElements()->getElementById($addressId, Address::class) : new Address();

                $address->id = $addressId;
            } else {
                $address = new Address();
            }

            $address->setAttributes($inventoryLocationAddress, false);

            // Only try and save if the inventory location is valid
            $hasAddressErrors = false;
            if ($isValid && !Craft::$app->getElements()->saveElement($address)) {
                $hasAddressErrors = $address->hasErrors();
            } else {
                // If we aren't saving the address let's validate it to show any potential errors
                if (!$address->validate()) {
                    $hasAddressErrors = $address->hasErrors();
                }
            }

            if ($hasAddressErrors) {
                $inventoryLocation->addModelErrors($address, 'address');
            }

            $inventoryLocation->setAddress($address);
        }

        $inventoryLocation->addressId = $inventoryLocation->getAddress()->id;

        if ($inventoryLocation->hasErrors() || !Plugin::getInstance()->getInventoryLocations()->saveInventoryLocation($inventoryLocation)) {
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
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionInventoryLocationsTableData(): Response
    {
        $this->requireAcceptsJson();
        $storeId = Craft::$app->getRequest()->getQueryParam('storeId', null);
        $storeId = $storeId ?: Plugin::getInstance()->getStores()->getPrimaryStore()->id;

        $view = $this->getView();
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        $data = [];
        foreach ($inventoryLocations as $inventoryLocation) {
            $id = $inventoryLocation->id;
            $deleteButtonId = sprintf("deleteButton-$id-%s", mt_rand());

            $deleteButton = Html::a('', '#', [
                'role' => 'button',
                'title' => Craft::t('commerce', 'Delete'),
                'class' => 'delete icon',
                'id' => $deleteButtonId,
            ]);

            $view->registerJsWithVars(fn($id, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
	e.preventDefault();
	const slideout = new Craft.CpModal('commerce/inventory-locations/prepare-delete-modal', $settings);
	slideout.on('close', (e) => {
	  window.InventoryLocationsAdminTable.reload();
	});
});
JS, [
                $deleteButtonId,
                ['params' => ['inventoryLocationId' => $id]],
            ]);

            /** @var InventoryLocation $inventoryLocation */
            $data[] = [
                'id' => $inventoryLocation->id,
                'title' => $inventoryLocation->name,
                'handle' => $inventoryLocation->handle,
                'address' => $inventoryLocation->getAddressLine(),
                'url' => $inventoryLocation->getCpEditUrl($storeId),
                'delete' => $deleteButton,
            ];
        }

        return $this->asJson([
            'data' => $data,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    /**
     * @return \craft\web\Response
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionPrepareDeleteModal(): Response
    {
        $this->requireAcceptsJson();
        $inventoryLocationId = Craft::$app->getRequest()->getRequiredParam('inventoryLocationId');
        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
        $allInventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        $destinationInventoryLocations = $allInventoryLocations
            ->filter(fn($location) => $location->id != $inventoryLocation->id);

        $destinationInventoryLocationsOptions = $destinationInventoryLocations
            ->map(fn($location) => ['value' => $location->id, 'label' => $location->name])->all();

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $inventoryLocation,
            'destinationInventoryLocation' => $destinationInventoryLocations->first(),
        ]);

        return $this->asCpModal()
            ->action('commerce/inventory-locations/deactivate')
            ->submitButtonLabel(Craft::t('commerce', 'Delete'))
            ->errorSummary('errors man')
            ->contentTemplate('commerce/store-management/inventory-locations/_deleteModal', [
                'deactivateInventoryLocation' => $deactivateInventoryLocation,
                'inventoryLocationOptions' => $destinationInventoryLocationsOptions,
            ]);
    }

    public function actionDeactivate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $inventoryLocationId = Craft::$app->getRequest()->getRequiredBodyParam('inventoryLocation');
        $destinationInventoryLocationId = Craft::$app->getRequest()->getRequiredBodyParam('destinationInventoryLocation');

        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
        $destinationInventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($destinationInventoryLocationId);

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $inventoryLocation,
            'destinationInventoryLocation' => $destinationInventoryLocation,
        ]);

        if (!Plugin::getInstance()->getInventoryLocations()->executeDeactivateInventoryLocation($deactivateInventoryLocation)) {
            return $this->asFailure(Craft::t('commerce', 'Inventory was not updated.',),
                ['errors' => $deactivateInventoryLocation->getErrors()]
            );
        }

        return $this->asJson(['success' => true]);
    }
}
