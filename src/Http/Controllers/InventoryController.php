<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Tab;
use CraftCms\Cms\Form\Nodes\Table as TableNode;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryItem;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Data\InventoryManualMovement;
use CraftCms\Commerce\Inventory\Data\InventoryTransaction;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class InventoryController
{
    use RespondsWithFlash;

    private const string ON_HAND = 'onHand';

    /** The stock level columns, in display order, keyed by the column they're totalled in. */
    private const array LEVEL_COLUMNS = [
        'reserved' => 'reservedTotal',
        'damaged' => 'damagedTotal',
        'safety' => 'safetyTotal',
        'qualityControl' => 'qualityControlTotal',
        'committed' => 'committedTotal',
        'available' => 'availableTotal',
        self::ON_HAND => 'onHandTotal',
        'incoming' => 'incomingTotal',
    ];

    public function __construct(
        private FormResolver $formResolver,
    ) {
    }

    public function editLocationLevels(Request $request, ?string $inventoryLocationHandle = null): CpScreenResponse|RedirectResponse
    {
        $inventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();
        $inventoryLocationHandle ??= $request->input('inventoryLocationHandle');

        if (!$inventoryLocationHandle) {
            abort_if($inventoryLocations->isEmpty(), 404, 'No inventory locations exist.');

            return redirect($inventoryLocations->first()->getCpManageInventoryUrl());
        }

        $currentLocation = $this->resolveInventoryLocation($inventoryLocationHandle);

        // Narrows the table to one item, for the "Manage" links on a purchasable's stock field.
        $inventoryItemId = $request->integer('inventoryItemId') ?: null;

        $table = TableNode::make('inventory-levels')
            ->columns([
                ['key' => 'purchasable', 'label' => t('Purchasable', category: 'commerce'), 'sortable' => true],
                ['key' => 'sku', 'label' => t('SKU', category: 'commerce'), 'sortable' => true],
                ...array_map(fn(string $type) => [
                    'key' => $type,
                    'label' => $this->levelLabel($type),
                    'sortable' => true,
                ], array_keys(self::LEVEL_COLUMNS)),
            ])
            ->dataUrl(action([self::class, 'inventoryLevelsTableData'], array_filter([
                'inventoryLocationId' => $currentLocation->id,
                'inventoryItemId' => $inventoryItemId,
            ])), 50)
            ->searchable(t('Search inventory', category: 'commerce'))
            ->toggleableColumns()
            ->emptyMessage(t('No inventory found.', category: 'commerce'));

        return new CpScreenResponse()
            ->title($currentLocation->getUiLabel() . ' ' . t('Inventory', category: 'commerce'))
            ->crumbs($this->crumbs($currentLocation))
            ->selectedSubnavItem('inventory')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make([$table]), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function inventoryLevelsTableData(Request $request): JsonResponse
    {
        $request->validate([
            'inventoryLocationId' => ['required', 'integer'],
            'inventoryItemId' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:250'],
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'array'],
        ]);

        $inventoryLocation = $this->resolveInventoryLocationById($request->integer('inventoryLocationId'));

        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 50);
        $inventoryItemId = $request->integer('inventoryItemId') ?: null;
        $search = $request->string('search')->trim()->toString();

        $query = app(Inventory::class)->getInventoryLevelQuery(inventoryLocationId: $inventoryLocation->id)
            ->where('inventoryLocationId', $inventoryLocation->id)
            ->addSelect(['purchasables.description', 'purchasables.sku'])
            ->leftJoin(Table::PURCHASABLES . ' as purchasables', 'ii.purchasableId', '=', 'purchasables.id')
            ->groupBy('purchasables.description', 'purchasables.sku')
            ->whereNotNull('elements.id');

        if ($inventoryItemId) {
            $query->where('inventoryItemId', $inventoryItemId);
        }

        if ($search !== '') {
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(fn($query) => $query
                ->where('purchasables.description', $likeOperator, "%$search%")
                ->orWhere('purchasables.sku', $likeOperator, "%$search%"));
        }

        $sortFields = [
            'purchasable' => 'purchasables.description',
            'sku' => 'purchasables.sku',
            ...self::LEVEL_COLUMNS,
        ];
        $sortField = $sortFields[(string)$request->input('sort.0.field')] ?? null;
        $sortDirection = $request->input('sort.0.direction');
        if ($sortField && in_array($sortDirection, ['asc', 'desc'], true)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $total = $query->getCountForPagination();
        $levels = $query->forPage($page, $perPage)->get();
        $purchasables = $this->purchasablesById($levels->pluck('purchasableId')->filter()->unique()->all());

        $rows = $levels->map(function(object $level) use ($inventoryLocation, $purchasables) {
            $level = (array)$level;
            $purchasable = $purchasables[$level['purchasableId']] ?? null;
            $sku = PurchasableHelper::isTempSku((string)$level['sku']) ? '' : (string)$level['sku'];

            $row = [
                'id' => $level['inventoryItemId'],
                'purchasable' => [
                    'label' => $purchasable?->getDescription() ?: (string)$level['description'],
                    'url' => $purchasable?->getCpEditUrl(),
                ],
                'sku' => [
                    'label' => $sku !== '' ? $sku : t('Edit'),
                    'url' => Url::cpUrl('commerce/inventory/item/' . $level['inventoryItemId']),
                ],
            ];

            foreach (self::LEVEL_COLUMNS as $type => $totalColumn) {
                $quantity = (int)$level[$totalColumn];
                $items = $this->levelActions($inventoryLocation, (int)$level['inventoryItemId'], $type, $quantity);

                $row[$type] = $items ? ['label' => (string)$quantity, 'items' => $items] : $quantity;
            }

            return $row;
        })->all();

        $lastPage = max(1, (int)ceil($total / $perPage));

        return new JsonResponse([
            'data' => TableNode::prepareRows($rows),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'next_page_url' => null,
                'prev_page_url' => null,
                'from' => $total ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($total, $page * $perPage),
            ],
        ]);
    }

    /**
     * The Form shown by a stock level's “Set Quantity” and “Adjust Quantity” modals. Its values are
     * posted to {@see updateLevels()} along with the request's own parameters.
     */
    public function prepareUpdateLevelsModal(Request $request): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocation = $this->resolveInventoryLocationById($request->integer('inventoryLocationId'));
        $inventoryItem = $this->resolveInventoryItem($request->integer('inventoryItemId'));

        $type = (string)$request->query('type');
        abort_unless($this->canAdjust($type), 400, 'Invalid type');

        $updateAction = InventoryUpdateQuantityType::tryFrom((string)$request->query('updateAction'));
        abort_if($updateAction === null, 400, 'Invalid updateAction');

        $isSet = $updateAction === InventoryUpdateQuantityType::SET;
        $quantity = (int)app(Inventory::class)->getInventoryLevel($inventoryItem, $inventoryLocation)->{$type . 'Total'};
        $label = $this->levelLabel($type);

        $form = Form::make([
            Callout::make('current-level', t('{item} currently has {quantity} {type} at {location}.', [
                'item' => $this->inventoryItemLabel($inventoryItem),
                'quantity' => $quantity,
                'type' => $label,
                'location' => $inventoryLocation->getUiLabel(),
            ], category: 'commerce')),
            Field::make(
                $isSet ? t('Set to', category: 'commerce') : t('Adjust by', category: 'commerce'),
                Number::make('quantity')->autofocus(),
            )->required(),
            Field::make(t('Notes', category: 'commerce'), Text::make('note')),
        ]);

        return new JsonResponse([
            'form' => $this->formResolver->resolve($form, new FormContext(values: [
                'quantity' => $isSet ? $quantity : 0,
                'note' => '',
            ])),
            'title' => $isSet
                ? t('Set {type} Quantity', ['type' => $label], category: 'commerce')
                : t('Adjust {type} Quantity', ['type' => $label], category: 'commerce'),
            'submitLabel' => t('Update', category: 'commerce'),
        ]);
    }

    public function updateLevels(Request $request): Response
    {
        $updateAction = InventoryUpdateQuantityType::tryFrom((string)$request->input('updateAction'));
        abort_if($updateAction === null, 400, 'Invalid updateAction');

        $type = (string)$request->input('type');
        abort_unless($this->canAdjust($type), 400, 'Invalid type');

        $inventoryLocationId = $request->integer('inventoryLocationId');
        // `ids` is the list form; `inventoryItemId` the single-item form the edit screen posts.
        $inventoryItemIds = array_filter(array_map(intval(...), [
            ...(array)$request->input('ids', []),
            ...(array)$request->input('inventoryItemId', []),
        ]));
        abort_if(!$inventoryLocationId || !$inventoryItemIds, 400, 'Missing inventoryLocationId or inventory item IDs');

        $quantity = $request->integer('quantity');
        $note = (string)$request->input('note');

        // We don't add zero amounts as transactions movements
        if ($updateAction === InventoryUpdateQuantityType::ADJUST && $quantity === 0) {
            return $this->asFailure(t('No inventory changes made.', category: 'commerce'));
        }

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

        if (!app(Inventory::class)->executeUpdateInventoryLevels($updateInventoryLevels)) {
            return $this->asFailure(t('Inventory was not updated.', category: 'commerce'), [
                'errors' => ['quantity' => [t('Inventory could not be set.', category: 'commerce')]],
            ]);
        }

        $resultingInventoryLevels = [];
        foreach ($updateInventoryLevels as $updateInventoryLevel) {
            /** @var UpdateInventoryLevel $updateInventoryLevel */
            $resultingInventoryLevels[] = app(Inventory::class)->getInventoryLevel($updateInventoryLevel->inventoryItemId, $updateInventoryLevel->inventoryLocationId);
        }

        return $this->asSuccess(t('Inventory updated.', category: 'commerce'), [
            'updatedItems' => collect($resultingInventoryLevels)->toArray(),
        ]);
    }

    /**
     * The Form shown by a stock level's “Move Inventory” modal. Its values are posted to
     * {@see saveInventoryMovement()}.
     */
    public function prepareMovementModal(Request $request): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $inventoryLocation = $this->resolveInventoryLocationById($request->integer('inventoryLocationId'));
        $inventoryItem = $this->resolveInventoryItem($request->integer('inventoryItemId'));

        $fromType = InventoryTransactionType::tryFrom((string)$request->query('type'));
        abort_unless($fromType && in_array($fromType, InventoryTransactionType::allowedManualMoveTransactionTypes(), true), 400, 'Invalid type');

        $toTypeOptions = collect(InventoryTransactionType::allowedManualMoveTransactionTypes())
            ->reject(fn(InventoryTransactionType $type) => $type === $fromType)
            ->map(fn(InventoryTransactionType $type) => ['value' => $type->value, 'label' => $type->typeAsLabel()])
            ->values()
            ->all();

        $available = app(Inventory::class)->getInventoryLevel($inventoryItem, $inventoryLocation)->getTotal($fromType);

        $form = Form::make([
            HiddenField::make(['inventoryMovement', 'inventoryItemId']),
            HiddenField::make(['inventoryMovement', 'fromInventoryLocationId']),
            HiddenField::make(['inventoryMovement', 'toInventoryLocationId']),
            HiddenField::make(['inventoryMovement', 'fromInventoryTransactionType']),
            Callout::make('current-level', t('{item} currently has {quantity} {type} at {location}.', [
                'item' => $this->inventoryItemLabel($inventoryItem),
                'quantity' => $available,
                'type' => $fromType->typeAsLabel(),
                'location' => $inventoryLocation->getUiLabel(),
            ], category: 'commerce')),
            Field::make(
                t('Quantity', category: 'commerce'),
                Number::make(['inventoryMovement', 'quantity'])->min(1)->max($available)->autofocus(),
            )->required(),
            Field::make(
                t('Move To', category: 'commerce'),
                Choice::make(['inventoryMovement', 'toInventoryTransactionType'])->options($toTypeOptions),
            )->required(),
            Field::make(t('Notes', category: 'commerce'), Text::make(['inventoryMovement', 'note'])),
        ]);

        return new JsonResponse([
            'form' => $this->formResolver->resolve($form, new FormContext(values: [
                'inventoryMovement' => [
                    'inventoryItemId' => $inventoryItem->id,
                    'fromInventoryLocationId' => $inventoryLocation->id,
                    'toInventoryLocationId' => $inventoryLocation->id,
                    'fromInventoryTransactionType' => $fromType->value,
                    'toInventoryTransactionType' => $toTypeOptions[0]['value'],
                    'quantity' => 1,
                    'note' => '',
                ],
            ])),
            'title' => t('Move {type} Inventory', ['type' => $fromType->typeAsLabel()], category: 'commerce'),
            'submitLabel' => t('Move', category: 'commerce'),
        ]);
    }

    public function saveInventoryMovement(Request $request): Response
    {
        $fromType = InventoryTransactionType::tryFrom((string)$request->input('inventoryMovement.fromInventoryTransactionType'));
        $toType = InventoryTransactionType::tryFrom((string)$request->input('inventoryMovement.toInventoryTransactionType'));
        abort_if(!$fromType || !$toType, 400, 'Invalid inventory transaction type');

        $fromLocation = app(InventoryLocations::class)->getInventoryLocationById($request->integer('inventoryMovement.fromInventoryLocationId'));
        $toLocation = app(InventoryLocations::class)->getInventoryLocationById($request->integer('inventoryMovement.toInventoryLocationId'));
        abort_if(!$fromLocation || !$toLocation, 400, 'Invalid inventory location');

        $quantity = $request->integer('inventoryMovement.quantity');

        if ($quantity === 0) {
            return $this->asSuccess(t('No inventory movements made.', category: 'commerce'));
        }

        $inventoryMovement = new InventoryManualMovement();
        $inventoryMovement->inventoryItemId = $request->integer('inventoryMovement.inventoryItemId');
        $inventoryMovement->fromInventoryLocation = $fromLocation;
        $inventoryMovement->toInventoryLocation = $toLocation;
        $inventoryMovement->fromInventoryTransactionType = $fromType;
        $inventoryMovement->toInventoryTransactionType = $toType;
        $inventoryMovement->quantity = $quantity;
        $inventoryMovement->note = (string)$request->input('inventoryMovement.note');

        if (!$inventoryMovement->validate()) {
            // Both rules are about whether the quantity can be moved, so that's where they're shown.
            return $this->asFailure(t('Inventory movement could not be saved.', category: 'commerce'), [
                'errors' => ['inventoryMovement.quantity' => $inventoryMovement->errors()->all()],
            ]);
        }

        /** @var InventoryMovementCollection $inventoryMovements */
        $inventoryMovements = InventoryMovementCollection::make()->push($inventoryMovement);

        if (!app(Inventory::class)->executeInventoryMovements($inventoryMovements)) {
            return $this->asFailure(t('Inventory movement could not be saved.', category: 'commerce'));
        }

        return $this->asSuccess(t('Inventory movement saved.', category: 'commerce'));
    }

    public function unfulfilledOrders(Request $request, string $inventoryLocationHandle): CpScreenResponse
    {
        $inventoryLocation = $this->resolveInventoryLocation($inventoryLocationHandle);
        $inventoryItem = $this->resolveInventoryItem($request->integer('inventoryItemId'));

        $orders = app(Inventory::class)->getUnfulfilledOrders($inventoryItem, $inventoryLocation);
        $formatter = app(Formatter::class);

        $table = TableNode::make('unfulfilled-orders')
            ->columns([
                ['key' => 'order', 'label' => t('Order', category: 'commerce')],
                ['key' => 'dateOrdered', 'label' => t('Date Ordered', category: 'commerce')],
            ])
            ->rows(array_map(fn(Order $order) => [
                'id' => $order->id,
                'order' => ['label' => (string)($order->reference ?: $order->getShortNumber()), 'url' => $order->getCpEditUrl()],
                'dateOrdered' => $order->dateOrdered ? $formatter->asDateTime($order->dateOrdered, 'short') : '',
            ], $orders))
            ->emptyMessage(t('No unfulfilled orders.', category: 'commerce'));

        $title = t('{count} Unfulfilled Orders', ['count' => count($orders)], category: 'commerce');

        return new CpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs($inventoryLocation, $this->inventoryItemLabel($inventoryItem)))
            ->selectedSubnavItem('inventory')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make([$table]), new FormContext()),
            ]);
    }

    public function itemEdit(int $inventoryItemId): CpScreenResponse
    {
        $inventoryItem = $this->resolveInventoryItem($inventoryItemId);

        $form = Form::make([
            Tab::make('details', t('Details', category: 'commerce'), [
                HiddenField::make('inventoryItemId'),
                Field::make(t('Country Code of Origin', category: 'commerce'), Text::make('countryCodeOfOrigin')),
                Field::make(t('Administrative Area Code of Origin', category: 'commerce'), Text::make('administrativeAreaCodeOfOrigin')),
                Field::make(t('Harmonized System Code', category: 'commerce'), Text::make('harmonizedSystemCode')),
            ]),
            Tab::make('history', t('History', category: 'commerce'), $this->historyNodes($inventoryItem)),
        ]);

        $title = $this->inventoryItemLabel($inventoryItem);

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
                new ActionItem()->label(t('Inventory', category: 'commerce'))->href(Url::cpUrl('commerce/inventory')),
                new ActionItem()->label($title),
            ])
            ->action('commerce/inventory/item-save')
            ->redirectUrl('commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($form, new FormContext(values: [
                    'inventoryItemId' => $inventoryItem->id,
                    'countryCodeOfOrigin' => $inventoryItem->countryCodeOfOrigin,
                    'administrativeAreaCodeOfOrigin' => $inventoryItem->administrativeAreaCodeOfOrigin,
                    'harmonizedSystemCode' => $inventoryItem->harmonizedSystemCode,
                ])),
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'itemSave']),
                ],
            ]);
    }

    public function itemSave(Request $request): Response
    {
        $inventoryItem = $this->resolveInventoryItem($request->integer('inventoryItemId'));

        $inventoryItem->countryCodeOfOrigin = (string)$request->input('countryCodeOfOrigin', $inventoryItem->countryCodeOfOrigin);
        $inventoryItem->administrativeAreaCodeOfOrigin = (string)$request->input('administrativeAreaCodeOfOrigin', $inventoryItem->administrativeAreaCodeOfOrigin);
        $inventoryItem->harmonizedSystemCode = (string)$request->input('harmonizedSystemCode', $inventoryItem->harmonizedSystemCode);

        if (!app(Inventory::class)->saveInventoryItem($inventoryItem)) {
            return $this->asModelFailure($inventoryItem, t('Couldn’t save inventory item.', category: 'commerce'), 'inventoryItem');
        }

        return $this->asModelSuccess($inventoryItem, t('Inventory Item saved.', category: 'commerce'), 'inventoryItem');
    }

    /**
     * Builds "Commerce / Inventory / {location}[ / $current]". The location crumb switches
     * between locations when there's more than one.
     *
     * @return list<ActionItem>
     */
    private function crumbs(InventoryLocation $currentLocation, ?string $current = null): array
    {
        $inventoryLocations = app(InventoryLocations::class)->getAllInventoryLocations();

        $locationCrumb = new ActionItem()
            ->label($currentLocation->getUiLabel())
            ->href($currentLocation->getCpManageInventoryUrl());

        if ($inventoryLocations->count() > 1) {
            $locationCrumb->items($inventoryLocations->map(fn(InventoryLocation $location) => new ActionItem()
                ->label($location->getUiLabel())
                ->href($location->getCpManageInventoryUrl())
                ->selected($location->id === $currentLocation->id))->values()->all());
        }

        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            new ActionItem()->label(t('Inventory', category: 'commerce'))->href(Url::cpUrl('commerce/inventory')),
            $locationCrumb,
            ...($current !== null ? [new ActionItem()->label($current)] : []),
        ];
    }

    /**
     * The actions offered on one stock level cell, as menu items. Setting, adjusting and moving
     * open a modal Form.
     *
     * @return list<array<string, mixed>>
     */
    private function levelActions(InventoryLocation $inventoryLocation, int $inventoryItemId, string $type, int $quantity): array
    {
        $params = [
            'inventoryLocationId' => $inventoryLocation->id,
            'inventoryItemId' => $inventoryItemId,
            'type' => $type,
        ];

        $items = [];

        if ($type === InventoryTransactionType::COMMITTED->value && $quantity > 0) {
            $items[] = [
                'label' => t('See Orders', category: 'commerce'),
                'url' => Url::cpUrl("commerce/inventory/levels/$inventoryLocation->handle/orders", ['inventoryItemId' => $inventoryItemId]),
            ];
        }

        if ($this->canAdjust($type)) {
            foreach ([
                [InventoryUpdateQuantityType::SET, t('Set Quantity', category: 'commerce')],
                [InventoryUpdateQuantityType::ADJUST, t('Adjust Quantity', category: 'commerce')],
            ] as [$updateAction, $label]) {
                $items[] = [
                    'label' => $label,
                    'modalUrl' => action([self::class, 'prepareUpdateLevelsModal']),
                    'actionUrl' => action([self::class, 'updateLevels']),
                    'params' => [...$params, 'updateAction' => $updateAction->value],
                ];
            }
        }

        if (
            $quantity > 0 &&
            in_array(InventoryTransactionType::tryFrom($type), InventoryTransactionType::allowedManualMoveTransactionTypes(), true)
        ) {
            $items[] = [
                'label' => t('Move Inventory', category: 'commerce'),
                'modalUrl' => action([self::class, 'prepareMovementModal']),
                'actionUrl' => action([self::class, 'saveInventoryMovement']),
                'params' => $params,
            ];
        }

        return $items;
    }

    private function canAdjust(string $type): bool
    {
        return $type === self::ON_HAND ||
            in_array(InventoryTransactionType::tryFrom($type), InventoryTransactionType::allowedManualAdjustmentTypes(), true);
    }

    private function levelLabel(string $type): string
    {
        return $type === self::ON_HAND
            ? t('On Hand', category: 'commerce')
            : InventoryTransactionType::from($type)->typeAsLabel();
    }

    private function inventoryItemLabel(InventoryItem $inventoryItem): string
    {
        $purchasable = $inventoryItem->getPurchasable('*');
        $sku = (string)$purchasable?->getSku();

        return match (true) {
            $sku !== '' && !PurchasableHelper::isTempSku($sku) => $sku,
            (string)$purchasable?->getDescription() !== '' => $purchasable->getDescription(),
            default => t('Inventory Item', category: 'commerce'),
        };
    }

    /**
     * Each location's transactions for the item, newest first.
     *
     * @return list<Heading|TableNode>
     */
    private function historyNodes(InventoryItem $inventoryItem): array
    {
        $formatter = app(Formatter::class);
        $nodes = [];

        foreach (app(InventoryLocations::class)->getAllInventoryLocations() as $location) {
            $transactions = app(Inventory::class)->getInventoryTransactions($inventoryItem, $location);

            $nodes[] = Heading::make("history-heading-$location->id", $location->getUiLabel());
            $nodes[] = TableNode::make("history-$location->id")
                ->columns([
                    ['key' => 'date', 'label' => t('Date', category: 'commerce')],
                    ['key' => 'type', 'label' => t('Type', category: 'commerce')],
                    ['key' => 'quantity', 'label' => t('Qty', category: 'commerce')],
                    ['key' => 'order', 'label' => t('Order', category: 'commerce')],
                    ['key' => 'note', 'label' => t('Note', category: 'commerce')],
                ])
                ->rows($transactions->map(function(InventoryTransaction $transaction) use ($formatter) {
                    $order = $transaction->getOrder();

                    return [
                        'date' => $transaction->dateCreated ? $formatter->asDateTime($transaction->dateCreated, 'short') : '',
                        'type' => InventoryTransactionType::tryFrom($transaction->type)?->typeAsLabel() ?? $transaction->type,
                        'quantity' => $transaction->quantity,
                        'order' => $order ? ['label' => (string)($order->reference ?: $order->getShortNumber()), 'url' => $order->getCpEditUrl()] : '',
                        'note' => $transaction->note,
                    ];
                })->values()->all())
                ->emptyMessage(t('No inventory transactions for this location.', category: 'commerce'))
                ->bordered();
        }

        return $nodes;
    }

    /**
     * Loads the page's purchasables with one query per element type.
     *
     * @param list<int> $ids
     * @return array<int, \CraftCms\Commerce\Purchasable\Elements\Purchasable>
     */
    private function purchasablesById(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $siteId = Sites::getCurrentSite()->id;
        $purchasables = [];

        $idsByType = DB::table(CraftTable::ELEMENTS)->whereIn('id', $ids)->get(['id', 'type'])->groupBy('type');

        /** @var class-string<\CraftCms\Commerce\Purchasable\Elements\Purchasable> $type */
        foreach ($idsByType as $type => $elements) {
            foreach ($type::find()->id($elements->pluck('id')->all())->siteId($siteId)->status(null)->all() as $purchasable) {
                $purchasables[$purchasable->id] = $purchasable;
            }
        }

        return $purchasables;
    }

    private function resolveInventoryLocation(string $handle): InventoryLocation
    {
        $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationByHandle($handle);
        abort_if(!$inventoryLocation, 404, 'Inventory location not found');

        return $inventoryLocation;
    }

    private function resolveInventoryLocationById(int $inventoryLocationId): InventoryLocation
    {
        abort_if(!$inventoryLocationId, 400, 'Missing inventoryLocationId');

        $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationById($inventoryLocationId);
        abort_if(!$inventoryLocation, 404, 'Inventory location not found');

        return $inventoryLocation;
    }

    private function resolveInventoryItem(int $inventoryItemId): InventoryItem
    {
        abort_if(!$inventoryItemId, 400, 'Missing inventoryItemId');
        abort_unless(
            app(Inventory::class)->getInventoryItemQuery()->where('id', $inventoryItemId)->exists(),
            404,
            'Inventory item not found',
        );

        return app(Inventory::class)->getInventoryItemById($inventoryItemId);
    }
}
