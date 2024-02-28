<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\inventory\DeactivateInventoryLocation;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
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
        $variables = [];

        $screen = $this->asCpScreen()
            ->title(Craft::t('commerce', 'Inventory Locations'))
            ->addCrumb(Craft::t('app', 'Inventory'), 'commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->pageSidebarTemplate('commerce/inventory/_sidebar', $variables)
            ->contentTemplate('commerce/inventory/locations/_index', $variables);

        $locationCount = count($inventoryLocations);
        $showNewButton = false;
        $userCanCreate = ($currentUser && $currentUser->can('commerce-createLocations'));

        if ($locationCount < Plugin::EDITION_PRO_STORE_LIMIT) {
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
            } else {
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

        $isNew = !$inventoryLocation->id;

        $addressCardId = 'inventory-location-address';
        $locationFieldHtml = Html::tag('div', Cp::elementCardHtml($address, [
            'context' => 'field',
            'inputName' => 'addressId',
            'showActionMenu' => true,
        ]), ['id' => $addressCardId]);

        $variables = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryLocation' => $inventoryLocation,
            'typeName' => Craft::t('commerce', 'Inventory Location'),
            'lowerTypeName' => Craft::t('commerce', 'inventory location'),
            'locationFieldHtml' => $locationFieldHtml,
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
            ->prepareScreen(function() use ($isNew, $addressCardId) {
                $view = Craft::$app->getView();
                if (!$isNew) {
                    $view->registerJsWithVars(fn($id, $elementType) => <<<JS
let storeLocation = document.querySelector('#' + $id);
storeLocation.addEventListener('dblclick', function() {
  const slideout = Craft.createElementEditor(
    $elementType,
    storeLocation.querySelector('.element.card')
  );
});
JS, [$addressCardId, 'craft\elements\Address']);
                }
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

    public function actionInventoryLocationsTableData()
    {
        $this->requireAcceptsJson();
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
                'url' => $inventoryLocation->cpEditUrl(),
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
     * @throws \craft\errors\DeprecationException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionPrepareDeleteModal()
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
            ->contentTemplate('commerce/inventory/locations/_deleteModal', [
                'deactivateInventoryLocation' => $deactivateInventoryLocation,
                'inventoryLocationOptions' => $destinationInventoryLocationsOptions,
            ]);
    }

    public function actionDeactivate()
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

        if (!Plugin::getInstance()->getInventoryLocations()->deactivateInventoryLocation($deactivateInventoryLocation)) {
            return $this->asFailure(Craft::t('commerce', 'Inventory was not updated.',),
                ['errors' => $deactivateInventoryLocation->getErrors()]
            );
        }

        return $this->asJson(['success' => true]);
    }
}
