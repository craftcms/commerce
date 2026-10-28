<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryFulfillmentLevel;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\commerce\web\assets\inventory\InventoryAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\enums\MenuItemType;
use craft\errors\DeprecationException;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\assets\htmx\HtmxAsset;
use craft\web\CpScreenResponseBehavior;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Inventory controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class InventoryController extends BaseCpController
{
    public $defaultAction = 'index';

    /**
     * @param int|null $inventoryItemId
     * @param InventoryItem|null $inventoryItem
     * @return Response
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    public function actionItemEdit(?int $inventoryItemId = null, ?InventoryItem $inventoryItem = null): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $view = Craft::$app->getView();
        $view->registerAssetBundle(HtmxAsset::class);

        if ($inventoryItemId !== null) {
            if ($inventoryItem === null) {
                $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);
            }
        } else {
            if ($inventoryItem === null) {
                throw new NotFoundHttpException('Inventory Item not found');
            }
        }

        $params = [
            'inventoryItem' => $inventoryItem,
        ];

        return $this->asCpScreen()
            ->title('Inventory Item')
            ->action('commerce/inventory/item-save')
            ->submitButtonLabel(Craft::t('app', 'Save'))
            ->redirectUrl('commerce/inventory')
            ->contentTemplate('commerce/inventory/item/_edit.twig', $params)
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->tabs(
                [
                    'details' => [
                        'label' => Craft::t('commerce', 'Details'),
                        'url' => '#details',
                    ],
                    'history' => [
                        'label' => Craft::t('commerce', 'History'),
                        'url' => '#history',
                    ],
                ])
            ->prepareScreen(
                function(Response $response, string $containerId) {
                    /** @var CpScreenResponseBehavior $response */
                    $this->getView()->registerJs('htmx.process(document.getElementById("' . $containerId . '"));');
                }
            );
    }

    /**
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionItemSave(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $inventoryItemId = Craft::$app->getRequest()->getRequiredParam('inventoryItemId');

        if ($inventoryItemId) {
            $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);
        } else {
            throw new HttpException(404);
        }

        $inventoryItem->countryCodeOfOrigin = Craft::$app->getRequest()->getParam('countryCodeOfOrigin', $inventoryItem->countryCodeOfOrigin);
        $inventoryItem->administrativeAreaCodeOfOrigin = Craft::$app->getRequest()->getParam('administrativeAreaCodeOfOrigin', $inventoryItem->administrativeAreaCodeOfOrigin);
        $inventoryItem->harmonizedSystemCode = Craft::$app->getRequest()->getParam('harmonizedSystemCode', $inventoryItem->harmonizedSystemCode);

        $success = Plugin::getInstance()->getInventory()->saveInventoryItem($inventoryItem);

        if (!$success) {
            return $this->asModelFailure($inventoryItem, Craft::t('app', 'Couldn’t save inventory item.'), 'inventoryItem');
        }

        return $this->asModelSuccess($inventoryItem, Craft::t('app', 'inventory Item saved.'), 'inventoryItem');
    }

    /**
     * commerce/inventory action
     *
     * @param string|null $inventoryLocationHandle
     * @return Response
     * @throws InvalidConfigException
     * @throws DeprecationException
     */
    public function actionEditLocationLevels(?string $inventoryLocationHandle = null): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');
        $view = Craft::$app->getView();
        $view->registerAssetBundle(InventoryAsset::class);

        $inventoryItemId = $this->request->getQueryParam('inventoryItemId'); // Used for quick link to manage stock
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        if (!$inventoryLocationHandle) {
            $inventoryLocationHandle = Craft::$app->getRequest()->getParam('inventoryLocationHandle');

            if (!$inventoryLocationHandle) {
                return $this->redirect('commerce/inventory/levels/' . $inventoryLocations[0]->handle);
            }
        }

        $search = Craft::$app->getRequest()->getQueryParam('search');

        $currentLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationByHandle($inventoryLocationHandle);
        $selectedItem = 'manage-' . $currentLocation->handle;
        $title = $currentLocation->getUiLabel() . ' ' . Craft::t('commerce', 'Inventory');

        $locationMenuItems = [];

        /** @var InventoryLocation $location */
        foreach ($inventoryLocations as $location) {
            $locationMenuItems[] = [
                'label' => $location->getUiLabel(),
                'url' => $location->getCpManageInventoryUrl(),
                'selected' => $location->handle === $inventoryLocationHandle,
            ];
        }
        $crumbs = [
            [
                'label' => Craft::t('commerce', 'Inventory'),
                'url' => 'commerce/inventory',
            ],
        ];

        if (count($locationMenuItems) > 1) {
            $crumbs[] = [
                'icon' => 'warehouse',
                'menu' => [
                    'label' => Craft::t('app', 'Select section'),
                    'items' => $locationMenuItems,
                ],
            ];
        } else {
            $crumbs[] = [
                'label' => $currentLocation->getUiLabel(),
                'url' => $currentLocation->getCpManageInventoryUrl(),
            ];
        }

        return $this->asCpScreen()
            ->title($title)
            ->site(Cp::requestedSite())
            ->selectableSites(Craft::$app->getSites()->getEditableSites())
            ->action(null)
            ->crumbs($crumbs)
            ->contentTemplate('commerce/inventory/levels/_index', compact(
                'inventoryLocations',
                'currentLocation',
                'inventoryItemId',
                'selectedItem',
                'search',
            ))
            ->selectedSubnavItem('inventory');
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function actionInventoryLevelsTableData(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $currentUser = Craft::$app->getUser()->getIdentity();
        $inventoryLevelsManagerContainerId = $this->request->getRequiredParam('containerId');
        $inventoryItemId = $this->request->getParam('inventoryItemId'); // Used for quick link to manage stock
        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 15);
        $offset = ($page - 1) * $limit;
        $inventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryLocationId');
        $search = $this->request->getParam('search');

        $inventoryQuery = Plugin::getInstance()->getInventory()->getInventoryLevelQuery(limit: $limit, offset: $offset, inventoryLocationId: $inventoryLocationId)
            ->andWhere(['inventoryLocationId' => $inventoryLocationId]);

        if ($inventoryItemId) {
            $inventoryQuery->andWhere(['inventoryItemId' => $inventoryItemId]);
        }

        $inventoryQuery->addSelect(['[[purchasables.description]]', '[[purchasables.sku]]']);
        $inventoryQuery->leftJoin(['purchasables' => Table::PURCHASABLES], '[[ii.purchasableId]] = [[purchasables.id]]');
        $inventoryQuery->addGroupBy(['[[purchasables.description]]', '[[purchasables.sku]]']);

        $inventoryQuery->andWhere(['not', ['elements.id' => null]]);

        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ilike' : 'like';
            $inventoryQuery->andWhere(['or', [$likeOperator, 'purchasables.description', $search], [$likeOperator, 'purchasables.sku', $search]]);
        }

        $sort = $this->request->getParam('sort');
        if ($sort) {
            $field = $sort[0]['sortField'];
            $direction = $sort[0]['direction'];

            // Validate the sorting inputs
            if (!in_array($direction, ['asc', 'desc']) ||
                !in_array($field, [
                    'item',
                    'sku',
                    'reservedTotal',
                    'damagedTotal',
                    'safetyTotal',
                    'qualityControlTotal',
                    'committedTotal',
                    'availableTotal',
                    'onHandTotal',
                    'incomingTotal',
                ])) {
                $field = null;
                $direction = null;
            }

            if ($field && $direction) {
                if ($field == 'sku') {
                    $field = 'purchasables.sku';
                }

                if ($field == 'item') {
                    $field = 'purchasables.description';
                }
                $inventoryQuery->addOrderBy($field . ' ' . $direction);
            }
        }

        $inventoryTableData = $inventoryQuery->all();

        $total = $inventoryQuery
            ->limit(null)
            ->offset(null)
            ->count();

        // Batch-load all purchasables for this page in one query per element type,
        // rather than one getElementById call per row.
        $requestedSite = Cp::requestedSite();
        $purchasableIds = array_unique(array_filter(array_column($inventoryTableData, 'purchasableId')));
        $purchasablesMap = [];
        if ($purchasableIds) {
            $elementTypes = (new Query())
                ->select(['id', 'type'])
                ->from(CraftTable::ELEMENTS)
                ->where(['id' => $purchasableIds])
                ->pairs();
            $byType = [];
            foreach ($elementTypes as $id => $type) {
                /** @var class-string<\craft\base\Element> $type */
                $byType[$type][] = $id;
            }
            foreach ($byType as $type => $ids) {
                foreach ($type::find()->id($ids)->siteId($requestedSite->id)->all() as $element) {
                    $purchasablesMap[$element->id] = $element;
                }
            }
        }

        $view = Craft::$app->getView();
        $time = microtime(true);
        foreach ($inventoryTableData as $key => &$inventoryLevel) {
            $id = $inventoryLevel['inventoryItemId'];
            /** @var ?Purchasable $purchasable */
            $purchasable = $purchasablesMap[$inventoryLevel['purchasableId']] ?? null;
            $inventoryItemDomId = sprintf("edit-$id-link-%s", mt_rand());
            if ($purchasable) {
                // When providing the `labelHtml` option we need to encode it ourselves
                $inventoryLevel['purchasable'] = Cp::chipHtml($purchasable, ['labelHtml' => Html::encode($purchasable->getDescription()), 'showActionMenu' => !$purchasable->getIsDraft() && $purchasable->canSave($currentUser)]);
            } else {
                $inventoryLevel['purchasable'] = Html::encode($inventoryLevel['description']);
            }
            if (PurchasableHelper::isTempSku($inventoryLevel['sku'])) {
                $inventoryLevel['sku'] = '';
            }

            // Ensure encoded SKU
            $inventoryLevel['sku'] = Html::tag('span', Html::a(Html::encode($inventoryLevel['sku']), "#", ['id' => "$inventoryItemDomId", 'class' => 'code']));
            $inventoryLevel['id'] = $id;

            $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
	e.preventDefault();
	const slideout = new Craft.CpScreenSlideout('commerce/inventory/item-edit', $params);
	slideout.on('close', (e) => {
	  $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
	});
});
JS, [
                $inventoryItemDomId,
                ['params' => ['inventoryItemId' => $id]],
                $inventoryLevelsManagerContainerId,
            ]);

            // @TODO Reduce the number of per-row modal click listeners registered here for inventory level columns
            $columnTypes = [...InventoryTransactionType::values(), 'onHand'];
            ArrayHelper::removeValue($columnTypes, 'fulfilled');
            foreach ($columnTypes as $type) {
                $items = [];
                $id = $inventoryLevel['id'];

                // Committed and reserved stock can both be traced back to the orders they're held for
                $orderLinksAction = match (true) {
                    $type == InventoryTransactionType::COMMITTED->value && $inventoryLevel['committedTotal'] > 0 => 'commerce/inventory/unfulfilled-orders',
                    $type == InventoryTransactionType::RESERVED->value && $inventoryLevel['reservedTotal'] > 0 => 'commerce/inventory/reserved-orders',
                    default => null,
                };

                if ($orderLinksAction) {
                    $showOrderLinksId = sprintf("$type-show-$id-order-links-%s", mt_rand());
                    $items['orderLinks'] = [
                        'type' => MenuItemType::Button,
                        'id' => $showOrderLinksId,
                        'label' => Craft::t('commerce', 'See Orders'),
                        'icon' => 'cart-shopping',
                    ];

                    $view->registerJsWithVars(fn($id, $action, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.CpModal($action, {
        containerElement: 'div',
        showSubmitButton: false,
        params: $params
    })
    modal.on('close', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $showOrderLinksId,
                        $orderLinksAction,
                        [
                            'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                $showSet = (
                    $type == 'onHand' ||
                    in_array(InventoryTransactionType::from($type), InventoryTransactionType::allowedManualAdjustmentTypes())
                );

                if ($showSet) {
                    $setId = sprintf("$type-update-level-$id-set-%s", mt_rand());
                    $items['set'] = [
                        'type' => MenuItemType::Button,
                        'id' => $setId,
                        'label' => Craft::t('commerce', 'Set Quantity'),
                        'icon' => 'bullseye',
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.UpdateInventoryLevelModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $setId,
                        [
                            'ids' => [$inventoryLevel['inventoryItemId']],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            'updateAction' => InventoryUpdateQuantityType::SET->value,
                            'type' => $type,
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                // Leave as it until we add more conditions for showing an adjustment
                $showAdjust = $showSet;

                if ($showAdjust) {
                    $adjustId = sprintf("$type-update-level-$id-adjust-%s", mt_rand());
                    $items['adjust'] = [
                        'type' => MenuItemType::Button,
                        'id' => $adjustId,
                        'icon' => 'arrow-trend-up',
                        'label' => Craft::t('commerce', 'Adjust Quantity'),
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.UpdateInventoryLevelModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $adjustId,
                        [
                            'ids' => [$inventoryLevel['inventoryItemId']],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            'updateAction' => InventoryUpdateQuantityType::ADJUST->value,
                            'type' => $type,
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                $showMovement = (
                    $type !== 'onHand' &&
                    in_array(InventoryTransactionType::from($type), InventoryTransactionType::allowedManualMoveTransactionTypes()) &&
                    $inventoryLevel[$type . 'Total'] > 0);

                if ($showMovement) {
                    $movementId = sprintf("$type-inventory-movement-$id-%s", mt_rand());
                    $items['movement'] = [
                        'type' => MenuItemType::Button,
                        'id' => $movementId,
                        'icon' => 'arrow-right',
                        'label' => Craft::t('commerce', 'Move Inventory'),
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.InventoryMovementModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      console.log(e);
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $movementId,
                        [
                            'inventoryMovement' => [
                                'note' => '',
                                'fromInventoryTransactionType' => $type,
                                'quantity' => '0',
                                'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                                'fromInventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            ],
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }


                $config = [
                    'class' => '',
                    'hiddenLabel' => Craft::t('app', 'Actions'),
                    'buttonAttributes' => [
                        'class' => ['action-btn'],
                        'data' => [
                            'icon' => 'ellipsis',
                            'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                            'inventoryLocationId' => $inventoryLocationId,
                            'type' => $type,
                        ],
                    ],
                ];
                $valueDiv = $inventoryLevel[$type . 'Total'];
                $actionButton = Cp::disclosureMenu($items, $config);
                $inventoryLevel[$type] = $valueDiv . (count($items) ? $actionButton : '');
            }
        }

        $totalTime = sprintf(' (time: %.3fs)', microtime(true) - $time);
        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $inventoryTableData,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionUpdateLevels(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $updateAction = InventoryUpdateQuantityType::from(Craft::$app->getRequest()->getRequiredParam('updateAction'));
        $quantity = (int)Craft::$app->getRequest()->getRequiredParam('quantity');
        $note = Craft::$app->getRequest()->getRequiredParam('note');
        $inventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryLocationId');
        $inventoryItemIds = Craft::$app->getRequest()->getRequiredParam('ids');
        $type = Craft::$app->getRequest()->getRequiredParam('type');

        // We don't add zero amounts as transactions movements
        if ($updateAction === InventoryUpdateQuantityType::ADJUST && $quantity == 0) {
            return $this->asFailure(Craft::t('commerce', 'No inventory changes made.'));
        }

        $errors = [];
        $updateInventoryLevels = UpdateInventoryLevelCollection::make();
        foreach ($inventoryItemIds as $inventoryItemId) {
            // Verbosely set property to show usages
            $updateInventoryLevel = new UpdateInventoryLevel();
            $updateInventoryLevel->type = $type;
            $updateInventoryLevel->updateAction = $updateAction;
            $updateInventoryLevel->inventoryItemId = $inventoryItemId;
            $updateInventoryLevel->inventoryLocationId = $inventoryLocationId;
            $updateInventoryLevel->quantity = $quantity;
            $updateInventoryLevel->note = $note;

            $updateInventoryLevels->push($updateInventoryLevel);
        }


        if (!Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels($updateInventoryLevels)) {
            $errors['updateQuantities'] = [Craft::t('commerce', 'Inventory could not be set.')];
        }

        if (count($errors) > 0) {
            return $this->asFailure(Craft::t('commerce', 'Inventory was not updated.',),
                ['errors' => $errors]
            );
        }

        $resultingInventoryLevels = [];
        foreach ($updateInventoryLevels as $updateInventoryLevel) {
            /** @var UpdateInventoryLevel $updateInventoryLevel */
            $resultingInventoryLevels[] = Plugin::getInstance()->getInventory()->getInventoryLevel($updateInventoryLevel->inventoryItemId, $updateInventoryLevel->inventoryLocationId);
        }

        return $this->asSuccess(Craft::t('commerce', 'Inventory updated.'), [
            'updatedItems' => collect($resultingInventoryLevels)->toArray(),
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function actionEditUpdateLevelsModal(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $inventoryLocationId = (int)$this->request->getParam('inventoryLocationId');
        $note = $this->request->getParam('note', '');
        $inventoryItemIds = (array)$this->request->getParam('ids', []); // param needs to be 'ids' to be compatible with admin table
        $updateAction = $this->request->getParam('updateAction', 'adjust');
        $quantity = (int)$this->request->getParam('quantity', 0);
        $type = $this->request->getRequiredParam('type');

        $inventoryLevels = [];
        foreach ($inventoryItemIds as $inventoryItemId) {
            $inventoryLevels[] = Plugin::getInstance()->getInventory()->getInventoryLevel((int)$inventoryItemId, $inventoryLocationId);
        }

        $params = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryItemIds' => $inventoryItemIds,
            'inventoryLevels' => $inventoryLevels,
            'updateAction' => $updateAction,
            'inventoryLocationOptions' => Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->mapWithKeys(fn($location) => [$location->id => $location->getUiLabel()])->all(),
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
        ];

        // Live preview refresh only swaps the preview region, leaving the form inputs untouched.
        if ($this->request->getParam('preview')) {
            return $this->asJson([
                'previewHtml' => Craft::$app->getView()->renderTemplate('commerce/inventory/levels/_updateInventoryLevelPreview', $params),
            ]);
        }

        return $this->asCpModal()
            ->action('commerce/inventory/update-levels')
            ->submitButtonLabel(Craft::t('commerce', 'Update'))
            ->contentTemplate('commerce/inventory/levels/_updateInventoryLevelModal', $params);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionSaveInventoryMovement(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $fromInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.toInventoryLocationId');
        $note = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.note');
        $fromInventoryTransactionType = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryTransactionType');
        $toInventoryTransactionType = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.toInventoryTransactionType');
        $inventoryItemId = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.inventoryItemId');
        $quantity = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.quantity');

        if ($quantity == 0) {
            return $this->asSuccess(Craft::t('commerce', 'No inventory movements made.'));
        }

        $inventoryMovement = new InventoryManualMovement();
        $inventoryMovement->inventoryItemId = $inventoryItemId;
        $inventoryMovement->fromInventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($fromInventoryLocationId);
        $inventoryMovement->toInventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($toInventoryLocationId);
        $inventoryMovement->fromInventoryTransactionType = InventoryTransactionType::from($fromInventoryTransactionType);
        $inventoryMovement->toInventoryTransactionType = InventoryTransactionType::from($toInventoryTransactionType);
        $inventoryMovement->quantity = $quantity;
        $inventoryMovement->note = $note;

        if ($inventoryMovement->validate()) {
            /** @var InventoryMovementCollection $inventoryMovementCollection */
            $inventoryMovementCollection = InventoryMovementCollection::make()->push($inventoryMovement);
            if (!Plugin::getInstance()->getInventory()->executeInventoryMovements($inventoryMovementCollection)) {
                return $this->asFailure(Craft::t('commerce', 'Inventory movement could not be saved.'));
            }
        }

        return $this->asSuccess(Craft::t('commerce', 'Inventory movement saved.'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionEditMovementModal(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $fromInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryMovement.toInventoryLocationId', $fromInventoryLocationId);
        $note = Craft::$app->getRequest()->getParam('inventoryMovement.note', '');
        $fromInventoryTransactionType = Craft::$app->getRequest()->getParam('inventoryMovement.fromInventoryTransactionType');
        $toInventoryTransactionType = Craft::$app->getRequest()->getParam('inventoryMovement.toInventoryTransactionType');
        $inventoryItemId = Craft::$app->getRequest()->getParam('inventoryMovement.inventoryItemId');
        $quantity = (int)Craft::$app->getRequest()->getParam('inventoryMovement.quantity', 0);

        $movableTo = collect(InventoryTransactionType::allowedManualMoveTransactionTypes())
            ->filter(fn($type) => $type->value !== $fromInventoryTransactionType)
            ->mapWithKeys(fn($type) => [$type->value => $type->typeAsLabel()]);

        $toInventoryTransactionType = InventoryTransactionType::tryFrom($toInventoryTransactionType);
        if (!$toInventoryTransactionType) {
            $toInventoryTransactionType = $movableTo->keys()->first();
        } else {
            $toInventoryTransactionType = $toInventoryTransactionType->value;
        }

        $inventoryMovement = new InventoryManualMovement();
        $inventoryMovement->inventoryItemId = $inventoryItemId;
        $inventoryMovement->fromInventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($fromInventoryLocationId);
        $inventoryMovement->toInventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($toInventoryLocationId);
        $inventoryMovement->fromInventoryTransactionType = InventoryTransactionType::from($fromInventoryTransactionType);
        $inventoryMovement->toInventoryTransactionType = InventoryTransactionType::from($toInventoryTransactionType);
        $inventoryMovement->quantity = $quantity;
        $inventoryMovement->note = $note;

        $fromLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($inventoryMovement->inventoryItemId, $inventoryMovement->fromInventoryLocation);
        $fromTotal = $fromLevel->{$fromInventoryTransactionType . 'Total'};

        $movableTo = $movableTo->toArray();
        $params = [
            'inventoryMovement' => $inventoryMovement,
            'toInventoryTransactionTypes' => $movableTo,
            'maxFromQuantity' => $fromTotal,
        ];

        // Live preview refresh only swaps the preview region, leaving the form inputs untouched.
        if ($this->request->getParam('preview')) {
            return $this->asJson([
                'previewHtml' => Craft::$app->getView()->renderTemplate('commerce/inventory/levels/_inventoryMovementPreview', $params),
            ]);
        }

        return $this->asCpModal()
            ->action('commerce/inventory/save-inventory-movement')
            ->submitButtonLabel(Craft::t('commerce', 'Move'))
            ->contentTemplate('commerce/inventory/levels/_inventoryMovementModal', $params);
    }

    /**
     * Returns a modal listing the orders that still need fulfilling from an inventory location's committed stock.
     *
     * @return Response
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
     */
    public function actionUnfulfilledOrders(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');
        Craft::$app->getView()->registerAssetBundle(InventoryAsset::class);

        [$inventoryItemId, $inventoryLocation] = $this->_getInventoryLevelParams();
        $inventory = Plugin::getInstance()->getInventory();

        $fulfillmentLevels = $this->_setFulfillmentLevelOrders(
            $inventory->getOutstandingInventoryFulfillmentLevels($inventoryItemId, $inventoryLocation->id)
        );
        $committedTotal = $inventory->getInventoryLevel($inventoryItemId, $inventoryLocation->id)?->committedTotal ?? 0;

        return $this->asCpModal()
            ->contentTemplate('commerce/inventory/levels/_unfulfilledOrdersModal', [
                'title' => Craft::t('commerce', '{count} Unfulfilled Orders', [
                    'count' => $fulfillmentLevels->pluck('orderId')->unique()->count(),
                ]),
                'inventoryLocation' => $inventoryLocation,
                'fulfillmentLevels' => $fulfillmentLevels,
                'committedTotal' => $committedTotal,
            ]);
    }

    /**
     * Returns a modal listing the orders whose shortfall is reserved at an inventory location, and where each order
     * is committed, so the stock can be transferred there to fulfill it.
     *
     * @return Response
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
     * @since 5.7.7
     */
    public function actionReservedOrders(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');
        Craft::$app->getView()->registerAssetBundle(InventoryAsset::class);

        [$inventoryItemId, $inventoryLocation] = $this->_getInventoryLevelParams();
        $inventory = Plugin::getInstance()->getInventory();

        $reservations = $inventory->getReservedQuantitiesByLineItem($inventoryItemId, $inventoryLocation->id);
        $orders = $this->_getOrdersById($reservations->pluck('orderId')->all());
        $committedLevels = $inventory
            ->getOutstandingInventoryFulfillmentLevels($inventoryItemId, null, $reservations->pluck('lineItemId')->all())
            ->groupBy('lineItemId');

        $rows = $reservations
            ->filter(fn(array $reservation) => isset($orders[$reservation['orderId']]))
            ->map(fn(array $reservation) => [
                'order' => $orders[$reservation['orderId']],
                'lineItem' => collect($orders[$reservation['orderId']]->getLineItems())->firstWhere('id', $reservation['lineItemId']),
                'reservedQuantity' => $reservation['reservedQty'],
                'committedLevels' => $committedLevels->get($reservation['lineItemId'], collect()),
            ])
            ->filter(fn(array $row) => $row['lineItem'] !== null)
            ->values();

        return $this->asCpModal()
            ->contentTemplate('commerce/inventory/levels/_reservedOrdersModal', [
                'title' => Craft::t('commerce', 'Orders'),
                'inventoryLocation' => $inventoryLocation,
                'rows' => $rows,
                'reservedTotal' => $inventory->getInventoryLevel($inventoryItemId, $inventoryLocation->id)?->reservedTotal ?? 0,
            ]);
    }

    /**
     * @return array{0: int, 1: InventoryLocation}
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    private function _getInventoryLevelParams(): array
    {
        $inventoryItemId = (int)$this->request->getRequiredParam('inventoryItemId');
        $inventoryLocationId = (int)$this->request->getRequiredParam('inventoryLocationId');
        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);

        if (!$inventoryLocation) {
            throw new NotFoundHttpException('Inventory location not found.');
        }

        return [$inventoryItemId, $inventoryLocation];
    }

    /**
     * Eager-loads the orders for a list of fulfillment levels, dropping any whose order can't be found (e.g. it's been deleted).
     *
     * @param Collection<InventoryFulfillmentLevel> $fulfillmentLevels
     * @return Collection<InventoryFulfillmentLevel>
     */
    private function _setFulfillmentLevelOrders(Collection $fulfillmentLevels): Collection
    {
        $orders = $this->_getOrdersById($fulfillmentLevels->pluck('orderId')->all());

        return $fulfillmentLevels
            ->filter(fn(InventoryFulfillmentLevel $fulfillmentLevel) => isset($orders[$fulfillmentLevel->orderId]))
            ->each(fn(InventoryFulfillmentLevel $fulfillmentLevel) => $fulfillmentLevel->setOrder($orders[$fulfillmentLevel->orderId]))
            ->values();
    }

    /**
     * @param int[] $orderIds
     * @return array<int, Order>
     */
    private function _getOrdersById(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));

        if (empty($orderIds)) {
            return [];
        }

        return Order::find()
            ->id($orderIds)
            ->status(null)
            ->indexBy('id')
            ->all();
    }
}
