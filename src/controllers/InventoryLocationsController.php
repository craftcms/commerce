<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\inventory\DeactivateInventoryLocation;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\errors\DeprecationException;
use craft\errors\ElementNotFoundException;
use craft\fieldlayoutelements\addresses\AddressField;
use craft\fieldlayoutelements\addresses\LabelField;
use craft\fieldlayoutelements\TextField;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\Controller;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Inventory Locations controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class InventoryLocationsController extends Controller
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
     * @return Response
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function actionIndex(): Response
    {
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $variables = [];

        $screen = $this->asCpScreen()
            ->title(Craft::t('commerce', 'Inventory Locations'))
            ->selectedSubnavItem('inventory-locations')
            ->contentTemplate('commerce/inventory-locations/_index', $variables);

        $locationCount = count($inventoryLocations);
        $showNewButton = false;
        $userCanCreate = ($currentUser && $currentUser->can('commerce-createLocations'));

        if ($locationCount < Plugin::EDITION_PRO_STORE_LIMIT) {
            $showNewButton = true;
        }

        if ($userCanCreate && $showNewButton) {
            $button = Html::a(
                Craft::t('commerce', 'New location'),
                'commerce/inventory-locations/new',
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
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
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

        Craft::$app->getView()->setNamespace('inventoryLocationAddress');

        $address = $inventoryLocation->getAddress();
        $fieldLayout = $address->getFieldLayout();

        $form = $fieldLayout->createForm($address);
        $form->tabIdPrefix = 'inventoryLocationAddress';
        $tabs = $form->getTabMenu();
        // Reset the `tabIdPrefix` so that the namespaces are correct for the inventory location fields
        $form->tabIdPrefix = null;

        // Remove the title/label field from the address field layout
        foreach ($form->tabs as &$tab) {
            $tab->elements = array_filter($tab->elements, function($element) {
                if (isset($element[0]) && $element[0] instanceof LabelField && $element[0]->attribute === 'title') {
                    return false;
                }

                return true;
            });
        }

        ArrayHelper::prependOrAppend($form->tabs[0]->elements, [
            null,
            false,
            Html::tag('hr')
        ], true);
        ArrayHelper::prependOrAppend($form->tabs[0]->elements, [
            null,
            false,
            Html::hiddenInput('id', $address->id),
        ], true);
        ArrayHelper::prependOrAppend($form->tabs[0]->elements, [
            null,
            false,
            Cp::textFieldHtml([
                'name' => 'handle',
                'id' => 'handle',
                'value' => $inventoryLocation->handle,
                'required' => true,
                'label' => Craft::t('commerce', 'Handle'),
                'errors' => $inventoryLocation->getErrors('handle'),
            ])
        ], true);
        ArrayHelper::prependOrAppend($form->tabs[0]->elements, [
            null,
            false,
            Cp::textFieldHtml([
                'name' => 'name',
                'id' => 'name',
                'value' => $inventoryLocation->name,
                'required' => true,
                'label' => Craft::t('commerce', 'Name'),
                'errors' => $inventoryLocation->getErrors('name'),
            ])
        ], true);
        ArrayHelper::prependOrAppend($form->tabs[0]->elements, [
            null,
            false,
            Html::hiddenInput('inventoryLocationId', $inventoryLocationId)
        ], true);

        $variables = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryLocation' => $inventoryLocation,
            'typeName' => Craft::t('commerce', 'Inventory Location'),
            'lowerTypeName' => Craft::t('commerce', 'inventory location'),
            'locationFieldHtml' => '',
            'addressField' => new AddressField(),
            'form' => $form,
            'countries' => Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language),
        ];

        return $this->asCpScreen()
            ->title($title)
            ->tabs($tabs)
            ->addCrumb(Craft::t('app', 'Inventory Locations'), 'commerce/inventory-locations')
            ->action('commerce/inventory-locations/save')
            ->redirectUrl('commerce/inventory-locations')
            ->selectedSubnavItem('inventory-locations')
            ->contentTemplate('commerce/inventory-locations/_edit', $variables)
            ->metaSidebarTemplate('commerce/inventory-locations/_sidebar', $variables);
    }

    /**
     * @return Response|null
     * @throws InvalidConfigException
     * @throws MethodNotAllowedHttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        // find the inventory location or make a new one
        $inventoryLocationId = Craft::$app->getRequest()->getBodyParam('inventoryLocationAddress[inventoryLocationId]');
        $inventoryLocation = null;

        if ($inventoryLocationId) {
            $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
        }

        if (!$inventoryLocation) {
            $inventoryLocation = new InventoryLocation();
        }

        $inventoryLocation->name = Craft::$app->getRequest()->getBodyParam('inventoryLocationAddress[name]');
        $inventoryLocation->handle = Craft::$app->getRequest()->getBodyParam('inventoryLocationAddress[handle]');

        // Pre-validate the inventory location so that we don't save the address if the rest isn't valid
        // This is to avoid orphaned addresses
        $isValid = $inventoryLocation->validate();

        if ($inventoryLocationAddress = Craft::$app->getRequest()->getBodyParam('inventoryLocationAddress')) {
            // Remove the non-address fields from the post data
            unset($inventoryLocationAddress['name'], $inventoryLocationAddress['handle'], $inventoryLocationAddress['inventoryLocationId']);

            $inventoryLocationAddress['title'] = $inventoryLocation->name;
            if ($isValid) {
                $addressId = $inventoryLocationAddress['id'] ?: null;
                $address = $addressId ? Craft::$app->getElements()->getElementById($addressId, Address::class) : new Address();

                $address->id = $addressId;
            } else {
                $address = new Address();
            }

            $address->setAttributes($inventoryLocationAddress, false);

            if (isset($inventoryLocationAddress['fields'])) {
                $address->setFieldValues($inventoryLocationAddress['fields']);
            }

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
     * @throws BadRequestHttpException
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function actionInventoryLocationsTableData(): Response
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
                'url' => $inventoryLocation->getCpEditUrl(),
                'delete' => $inventoryLocations->count() > 1 ? $deleteButton : '',
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

        if (empty($destinationInventoryLocationsOptions)) {
            // throw exception not allowed to delete
            throw new \Exception('Can not delete last inventory location.');
        }

        $deactivateInventoryLocation = new DeactivateInventoryLocation([
            'inventoryLocation' => $inventoryLocation,
            'destinationInventoryLocation' => $destinationInventoryLocations->first(),
        ]);

        return $this->asCpModal()
            ->action('commerce/inventory-locations/deactivate')
            ->submitButtonLabel(Craft::t('commerce', 'Delete'))
            ->errorSummary('Can not delete inventory location.')
            ->contentTemplate('commerce/inventory-locations/_deleteModal', [
                'deactivateInventoryLocation' => $deactivateInventoryLocation,
                'inventoryLocationOptions' => $destinationInventoryLocationsOptions,
            ]);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws InvalidConfigException
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
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
