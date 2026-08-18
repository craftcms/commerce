<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\base\Purchasable;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\Plugin;
use craft\commerce\web\assets\inventory\InventoryAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\enums\MenuItemType;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use CraftCms\Cms\Support\Html;
use craft\web\assets\htmx\HtmxAsset;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpModalResponse;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

readonly class InventoryController
{
    use RespondsWithFlash;

    public function itemEdit(?int $inventoryItemId = null): CpScreenResponse
    {
        \Craft::$app->getView()->registerAssetBundle(HtmxAsset::class);

        abort_if($inventoryItemId === null, 404, 'Inventory Item not found');

        $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);

        return new CpScreenResponse()
            ->title('Inventory Item')
            ->action('commerce/inventory/item-save')
            ->submitButtonLabel(t('Save'))
            ->redirectUrl('commerce/inventory')
            ->contentTemplate('commerce/inventory/item/_edit.twig', ['inventoryItem' => $inventoryItem])
            ->addCrumb(t('Inventory', category: 'commerce'), 'commerce/inventory')
            ->tabs([
                'details' => [
                    'label' => t('Details', category: 'commerce'),
                    'url' => '#details',
                ],
                'history' => [
                    'label' => t('History', category: 'commerce'),
                    'url' => '#history',
                ],
            ])
            ->prepareScreen(function($screen, string $containerId) {
                HtmlStack::js('htmx.process(document.getElementById("' . $containerId . '"));');
            });
    }

    public function itemSave(Request $request): Response
    {
        $inventoryItemId = $request->input('inventoryItemId');
        abort_if(!$inventoryItemId, 404);

        $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById((int)$inventoryItemId);
        abort_if(!$inventoryItem, 404);

        $inventoryItem->countryCodeOfOrigin = $request->input('countryCodeOfOrigin', $inventoryItem->countryCodeOfOrigin);
        $inventoryItem->administrativeAreaCodeOfOrigin = $request->input('administrativeAreaCodeOfOrigin', $inventoryItem->administrativeAreaCodeOfOrigin);
        $inventoryItem->harmonizedSystemCode = $request->input('harmonizedSystemCode', $inventoryItem->harmonizedSystemCode);

        $success = Plugin::getInstance()->getInventory()->saveInventoryItem($inventoryItem);

        if (!$success) {
            return $this->asModelFailure($inventoryItem, t('Couldn\'t save inventory item.'), 'inventoryItem');
        }

        return $this->asModelSuccess($inventoryItem, t('Inventory Item saved.'), 'inventoryItem');
    }

    public function editLocationLevels(Request $request, ?string $inventoryLocationHandle = null): Response
    {
        \Craft::$app->getView()->registerAssetBundle(InventoryAsset::class);

        $inventoryItemId = $request->query('inventoryItemId'); // Used for quick link to manage stock
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        if (!$inventoryLocationHandle) {
            $inventoryLocationHandle = $request->input('inventoryLocationHandle');

            if (!$inventoryLocationHandle) {
                return redirect('commerce/inventory/levels/' . $inventoryLocations[0]->handle);
            }
        }

        $search = $request->query('search');

        $currentLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationByHandle($inventoryLocationHandle);
        $selectedItem = 'manage-' . $currentLocation->handle;
        $title = $currentLocation->getUiLabel() . ' ' . t('Inventory', category: 'commerce');

        $locationMenuItems = [];

        foreach ($inventoryLocations as $location) {
            $locationMenuItems[] = [
                'label' => $location->getUiLabel(),
                'url' => $location->getCpManageInventoryUrl(),
                'selected' => $location->handle === $inventoryLocationHandle,
            ];
        }
        $crumbs = [
            [
                'label' => t('Inventory', category: 'commerce'),
                'url' => 'commerce/inventory',
            ],
        ];

        if (count($locationMenuItems) > 1) {
            $crumbs[] = [
                'icon' => 'warehouse',
                'menu' => [
                    'label' => t('Select section'),
                    'items' => $locationMenuItems,
                ],
            ];
        } else {
            $crumbs[] = [
                'label' => $currentLocation->getUiLabel(),
                'url' => $currentLocation->getCpManageInventoryUrl(),
            ];
        }

        return new CpScreenResponse()
            ->title($title)
            ->site(Cp::requestedSite())
            ->selectableSites(Sites::getEditableSites())
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

    public function inventoryLevelsTableData(Request $request): Response
    {
        $currentUser = currentUserElement();
        $inventoryLevelsManagerContainerId = $request->input('containerId');
        abort_if(!$inventoryLevelsManagerContainerId, 400, 'Missing containerId');

        $inventoryItemId = $request->input('inventoryItemId'); // Used for quick link to manage stock
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('per_page', 15);
        $offset = ($page - 1) * $limit;
        $inventoryLocationId = (int)$request->input('inventoryLocationId');
        $search = $request->input('search');

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
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $inventoryQuery->andWhere(['or', [$likeOperator, 'purchasables.description', $search], [$likeOperator, 'purchasables.sku', $search]]);
        }

        $sort = $request->input('sort');
        if ($sort) {
            $field = $sort[0]['sortField'];
            $direction = $sort[0]['direction'];

            // Validate the sorting inputs
            if (
                !in_array($direction, ['asc', 'desc']) ||
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
                ])
            ) {
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
            $elementTypes = new Query()
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

            HtmlStack::jsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
\$('#' + $id).on('click', (e) => {
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
            $columnTypes = array_filter($columnTypes, fn($type) => $type !== 'fulfilled');
            foreach ($columnTypes as $type) {
                $items = [];
                $id = $inventoryLevel['id'];

                $showOrderLinks = (
                    $type == InventoryTransactionType::COMMITTED->value &&
                    $inventoryLevel['committedTotal'] > 0
                );

                if ($showOrderLinks) {
                    $showOrderLinksId = sprintf("$type-show-$id-order-links-%s", mt_rand());
                    $items['orderLinks'] = [
                        'type' => MenuItemType::Button,
                        'id' => $showOrderLinksId,
                        'label' => t('See Orders', category: 'commerce'),
                        'icon' => 'cart-shopping',
                    ];

                    HtmlStack::jsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
\$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.CpModal('commerce/inventory/unfulfilled-orders', {
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
                        'label' => t('Set Quantity', category: 'commerce'),
                        'icon' => 'bullseye',
                    ];

                    HtmlStack::jsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
\$('#' + $id).on('click', (e) => {
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
                        'label' => t('Adjust Quantity', category: 'commerce'),
                    ];

                    HtmlStack::jsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
\$('#' + $id).on('click', (e) => {
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
                        'label' => t('Move Inventory', category: 'commerce'),
                    ];

                    HtmlStack::jsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
\$('#' + $id).on('click', (e) => {
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
                    'hiddenLabel' => t('Actions'),
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
        unset($inventoryLevel);

        return response()->json([
            'pagination' => AdminTable::paginationLinks($page, (int)$total, $limit),
            'data' => $inventoryTableData,
            'headHtml' => HtmlStack::headHtml(),
            'bodyHtml' => HtmlStack::bodyHtml(),
        ]);
    }

    public function updateLevels(Request $request): Response
    {
        $updateAction = InventoryUpdateQuantityType::from($request->input('updateAction'));
        $quantity = (int)$request->input('quantity');
        $note = $request->input('note');
        $inventoryLocationId = (int)$request->input('inventoryLocationId');
        $inventoryItemIds = $request->input('ids');
        $type = $request->input('type');

        // We don't add zero amounts as transactions movements
        if ($updateAction === InventoryUpdateQuantityType::ADJUST && $quantity == 0) {
            return $this->asFailure(t('No inventory changes made.', category: 'commerce'));
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
            $errors['updateQuantities'] = [t('Inventory could not be set.', category: 'commerce')];
        }

        if (count($errors) > 0) {
            return $this->asFailure(t('Inventory was not updated.', category: 'commerce'), ['errors' => $errors]);
        }

        $resultingInventoryLevels = [];
        foreach ($updateInventoryLevels as $updateInventoryLevel) {
            /** @var UpdateInventoryLevel $updateInventoryLevel */
            $resultingInventoryLevels[] = Plugin::getInstance()->getInventory()->getInventoryLevel($updateInventoryLevel->inventoryItemId, $updateInventoryLevel->inventoryLocationId);
        }

        return $this->asSuccess(t('Inventory updated.', category: 'commerce'), [
            'updatedItems' => collect($resultingInventoryLevels)->toArray(),
        ]);
    }

    public function editUpdateLevelsModal(Request $request): Response
    {
        $inventoryLocationId = (int)$request->input('inventoryLocationId');
        $note = $request->input('note', '');
        $inventoryItemIds = (array)$request->input('ids', []); // param needs to be 'ids' to be compatible with admin table
        $updateAction = $request->input('updateAction', 'adjust');
        $quantity = (int)$request->input('quantity', 0);
        $type = $request->input('type');
        abort_if(!$type, 400, 'Missing type');

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
        if ($request->input('preview')) {
            return response()->json([
                'previewHtml' => template('commerce/inventory/levels/_updateInventoryLevelPreview', $params, TemplateMode::Cp),
            ]);
        }

        return new CpModalResponse()
            ->action('commerce/inventory/update-levels')
            ->submitButtonLabel(t('Update', category: 'commerce'))
            ->contentTemplate('commerce/inventory/levels/_updateInventoryLevelModal', $params);
    }

    public function saveInventoryMovement(Request $request): Response
    {
        $fromInventoryLocationId = (int)$request->input('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)$request->input('inventoryMovement.toInventoryLocationId');
        $note = $request->input('inventoryMovement.note');
        $fromInventoryTransactionType = $request->input('inventoryMovement.fromInventoryTransactionType');
        $toInventoryTransactionType = $request->input('inventoryMovement.toInventoryTransactionType');
        $inventoryItemId = $request->input('inventoryMovement.inventoryItemId');
        $quantity = (int)$request->input('inventoryMovement.quantity');

        if ($quantity == 0) {
            return $this->asSuccess(t('No inventory movements made.', category: 'commerce'));
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
                return $this->asFailure(t('Inventory movement could not be saved.', category: 'commerce'));
            }
        }

        return $this->asSuccess(t('Inventory movement saved.', category: 'commerce'));
    }

    public function editMovementModal(Request $request): Response
    {
        $fromInventoryLocationId = (int)$request->input('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)$request->input('inventoryMovement.toInventoryLocationId', $fromInventoryLocationId);
        $note = $request->input('inventoryMovement.note', '');
        $fromInventoryTransactionType = $request->input('inventoryMovement.fromInventoryTransactionType');
        $toInventoryTransactionType = $request->input('inventoryMovement.toInventoryTransactionType');
        $inventoryItemId = $request->input('inventoryMovement.inventoryItemId');
        $quantity = (int)$request->input('inventoryMovement.quantity', 0);

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
        if ($request->input('preview')) {
            return response()->json([
                'previewHtml' => template('commerce/inventory/levels/_inventoryMovementPreview', $params, TemplateMode::Cp),
            ]);
        }

        return new CpModalResponse()
            ->action('commerce/inventory/save-inventory-movement')
            ->submitButtonLabel(t('Move', category: 'commerce'))
            ->contentTemplate('commerce/inventory/levels/_inventoryMovementModal', $params);
    }

    public function unfulfilledOrders(Request $request): CpModalResponse
    {
        $inventoryLocationId = $request->input('inventoryLocationId');
        $inventoryItemId = $request->input('inventoryItemId');

        $orders = Plugin::getInstance()->getInventory()->getUnfulfilledOrders($inventoryItemId, $inventoryLocationId);

        $title = t('{count} Unfulfilled Orders', ['count' => count($orders)], category: 'commerce');

        return new CpModalResponse()
            ->contentTemplate('commerce/inventory/levels/_unfulfilledOrdersModal', compact(
                'title',
                'orders'
            ));
    }
}
