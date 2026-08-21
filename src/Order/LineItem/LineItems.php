<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\LineItem;

use craft\commerce\Plugin;
use CraftCms\Commerce\Helpers\LineItem as LineItemHelper;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\LineItemEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\Models\LineItem as LineItemRecord;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

/**
 * Line item service.
 *
 * Bridges between the rich {@see LineItem} data object the rest of the codebase works with, and the
 * thin {@see LineItemRecord} Eloquent model used purely for persistence — see the class docblock on
 * `LineItem` for why they're kept separate rather than a single unified Eloquent class.
 */
#[Singleton]
class LineItems
{
    /**
     * @event LineItemEvent The event that is triggered before a line item is saved.
     */
    public const string EVENT_BEFORE_SAVE_LINE_ITEM = 'beforeSaveLineItem';

    /**
     * @event LineItemEvent The event that is triggered after a line item is saved.
     */
    public const string EVENT_AFTER_SAVE_LINE_ITEM = 'afterSaveLineItem';

    /**
     * @event LineItemEvent The event that is triggered after a line item has been created from a purchasable.
     */
    public const string EVENT_CREATE_LINE_ITEM = 'createLineItem';

    /**
     * @event LineItemEvent The event that is triggered as a line item is being populated from a purchasable.
     */
    public const string EVENT_POPULATE_LINE_ITEM = 'populateLineItem';

    /**
     * Returns an order's line items, per the order's ID.
     *
     * @return LineItem[] An array of all the line items for the matched order.
     */
    public function getAllLineItemsByOrderId(int $orderId): array
    {
        return LineItemRecord::query()
            ->where('orderId', $orderId)
            ->orderByDesc('dateCreated')
            ->get()
            ->map(fn(LineItemRecord $record) => $this->_toData($record))
            ->all();
    }

    /**
     * Takes an order, a purchasable ID, options, and resolves it to a line item.
     *
     * If a line item is found for that order ID with those exact options, that line item is
     * returned. Otherwise, a new line item is returned.
     *
     * @throws \Exception
     */
    public function resolveLineItem(Order $order, int $purchasableId, array $options = [], array $params = []): LineItem
    {
        $signature = LineItemHelper::generateOptionsSignature($options);

        $record = $order->id
            ? LineItemRecord::query()
                ->where('orderId', $order->id)
                ->where('purchasableId', $purchasableId)
                ->where('optionsSignature', $signature)
                ->first()
            : null;

        if ($record) {
            return $this->_toData($record);
        }

        $params = array_merge([
            'qty' => 1,
            'options' => $options,
            'note' => '',
            'purchasableId' => $purchasableId,
        ], $params);

        return $this->create($order, $params);
    }

    /**
     * @throws \Exception
     */
    public function resolveCustomLineItem(Order $order, string $sku, array $options = []): LineItem
    {
        $signature = LineItemHelper::generateOptionsSignature($options);

        $record = $order->id
            ? LineItemRecord::query()
                ->where('orderId', $order->id)
                ->where('sku', $sku)
                ->where('optionsSignature', $signature)
                ->where('type', LineItemType::Custom->value)
                ->first()
            : null;

        if ($record) {
            return $this->_toData($record);
        }

        return $this->create($order, [
            'sku' => $sku,
            'options' => $options,
        ], LineItemType::Custom);
    }

    /**
     * Save a line item.
     *
     * @param LineItem $lineItem The line item to save.
     * @param bool $runValidation Whether the Line Item should be validated.
     * @TODO `$runValidation` is not yet wired up to a real validator; `LineItem::getValidationRules()`
     * still returns legacy-shaped rule arrays pending the broader migration of line item validation
     * onto the new Ruleset system.
     */
    public function saveLineItem(LineItem $lineItem, bool $runValidation = true): bool
    {
        $isNewLineItem = !$lineItem->id;

        // TODO: migrate event firing to Laravel once event system is bridged
        $legacyService = Plugin::getInstance()->getLineItems();

        if ($legacyService->hasEventHandlers(self::EVENT_BEFORE_SAVE_LINE_ITEM)) {
            $event = new LineItemEvent(
                lineItem: $lineItem,
                isNew: $isNewLineItem,
            );
            /** @phpstan-ignore-next-line argument.type (TODO: migrate event firing to Laravel once event system is bridged) */
            $legacyService->trigger(self::EVENT_BEFORE_SAVE_LINE_ITEM, $event);
        }

        $record = $this->_toRecord($lineItem);

        // Save this information for all line item types, even though live lookups will happen for line items with purchasables
        $record->hasFreeShipping = $lineItem->getHasFreeShipping();
        $record->isPromotable = $lineItem->getIsPromotable();
        $record->isShippable = $lineItem->getIsShippable();
        $record->isTaxable = $lineItem->getIsTaxable();

        $record->sku = $lineItem->getSku();
        $record->description = $lineItem->getDescription();
        $record->optionsSignature = $lineItem->getOptionsSignature();

        $record->promotionalAmount = $lineItem->getPromotionalAmount();
        $record->salePrice = $lineItem->getSalePrice();
        $record->total = $lineItem->getTotal();
        $record->subtotal = $lineItem->getSubtotal();

        $success = DB::transaction(fn() => $record->save());

        if ($success) {
            $lineItem->id = $record->id;
            $lineItem->uid = $record->uid;
            $lineItem->dateCreated = $record->dateCreated;
            $lineItem->dateUpdated = $record->dateUpdated;
        }

        if ($success && $legacyService->hasEventHandlers(self::EVENT_AFTER_SAVE_LINE_ITEM)) {
            $event = new LineItemEvent(
                lineItem: $lineItem,
                isNew: $isNewLineItem,
            );
            /** @phpstan-ignore-next-line argument.type (TODO: migrate event firing to Laravel once event system is bridged) */
            $legacyService->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, $event);
        }

        return $success;
    }

    /**
     * Get a line item by its ID.
     */
    public function getLineItemById(int $id): ?LineItem
    {
        $record = LineItemRecord::query()->find($id);

        return $record ? $this->_toData($record) : null;
    }

    /**
     * @throws \Exception
     */
    public function create(Order $order, array $params = [], LineItemType $type = LineItemType::Purchasable): LineItem
    {
        $params = array_merge([
            'qty' => 1,
            'options' => [],
            'note' => '',
        ], $params);

        $params['type'] = $type;

        if ($type === LineItemType::Purchasable && empty($params['purchasableId']) && empty($params['purchasable'])) {
            throw new \InvalidArgumentException('Purchasable ID or Purchasable must be set');
        }

        $explicitPurchasable = $params['purchasable'] ?? null;
        unset($params['purchasable']);

        $lineItem = new LineItem($params);
        $lineItem->setOrder($order);

        if ($explicitPurchasable instanceof PurchasableInterface) {
            $lineItem->setPurchasable($explicitPurchasable);
        }

        if ($lineItem->type === LineItemType::Purchasable) {
            $purchasable = $lineItem->getPurchasable();

            if ($purchasable) {
                $lineItem->setPurchasable($purchasable);
                $lineItem->populate($purchasable);
            } else {
                throw new \InvalidArgumentException('Invalid purchasable ID');
            }
        } else {
            $lineItem->populate();
        }

        // TODO: migrate event firing to Laravel once event system is bridged
        $legacyService = Plugin::getInstance()->getLineItems();
        if ($legacyService->hasEventHandlers(self::EVENT_CREATE_LINE_ITEM)) {
            $event = new LineItemEvent(
                lineItem: $lineItem,
                isNew: true,
            );
            /** @phpstan-ignore-next-line argument.type (TODO: migrate event firing to Laravel once event system is bridged) */
            $legacyService->trigger(self::EVENT_CREATE_LINE_ITEM, $event);
        }

        $lineItem->refresh();

        return $lineItem;
    }

    /**
     * Deletes all line items associated with an order, per the order's ID.
     *
     * @return bool whether any line items were deleted
     */
    public function deleteAllLineItemsByOrderId(int $orderId): bool
    {
        return (bool)LineItemRecord::query()->where('orderId', $orderId)->delete();
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadLineItemsForOrders(array $orders): array
    {
        $orderIds = collect($orders)->pluck('id')->filter()->all();

        $lineItemsByOrderId = LineItemRecord::query()
            ->whereIn('orderId', $orderIds)
            ->orderByDesc('dateCreated')
            ->get()
            ->map(fn(LineItemRecord $record) => $this->_toData($record))
            ->groupBy('orderId');

        foreach ($orders as $key => $order) {
            if ($lineItemsByOrderId->has($order->id)) {
                $order->setLineItems($lineItemsByOrderId->get($order->id)->all());
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * @throws \Throwable
     */
    public function orderCompleteHandler(LineItem $lineItem, Order $order): void
    {
        // Called the after order complete method for the purchasable if there is one
        if ($lineItem->type === LineItemType::Purchasable && $lineItem->getPurchasable()) {
            $lineItem->getPurchasable()->afterOrderComplete($order, $lineItem);
        }

        // Retrieve the default status for the current line item. This is a chance for
        // developers to hook into an event for finer control
        $defaultStatus = app(LineItemStatuses::class)->getDefaultLineItemStatusForLineItem($lineItem);
        if (!$defaultStatus) {
            return;
        }

        // Set the status ID and save the line item
        $lineItem->setLineItemStatus($defaultStatus);
        $this->saveLineItem($lineItem, false);
    }

    /**
     * Hydrates a rich {@see LineItem} data object from a persisted {@see LineItemRecord} row.
     *
     * Built up via explicit property/setter assignment rather than passing `$record->getAttributes()`
     * into the constructor's config array — several persisted columns (`optionsSignature`, `salePrice`,
     * `subtotal`, `total`, `promotionalAmount`) back pure computed, setter-less getters on the Data
     * object, so passing them as config would throw "Setting read-only property".
     */
    private function _toData(LineItemRecord $record): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->id = $record->id;
        $lineItem->type = $record->type;
        $lineItem->orderId = $record->orderId;
        $lineItem->purchasableId = $record->purchasableId;
        $lineItem->lineItemStatusId = $record->lineItemStatusId;
        $lineItem->taxCategoryId = $record->taxCategoryId;
        $lineItem->shippingCategoryId = $record->shippingCategoryId;
        $lineItem->qty = $record->qty;
        $lineItem->note = $record->note ?? '';
        $lineItem->privateNote = $record->privateNote ?? '';
        $lineItem->weight = $record->weight ?? 0;
        $lineItem->length = $record->length ?? 0;
        $lineItem->height = $record->height ?? 0;
        $lineItem->width = $record->width ?? 0;
        $lineItem->uid = $record->uid;
        $lineItem->dateCreated = $record->dateCreated;
        $lineItem->dateUpdated = $record->dateUpdated;
        $lineItem->setOptions($record->options ?? []);
        $lineItem->setSnapshot($record->snapshot ?? []);
        $lineItem->setPrice($record->price ?? 0);
        $lineItem->setPromotionalPrice($record->promotionalPrice);
        $lineItem->setSku($record->sku);
        $lineItem->setDescription($record->description);
        $lineItem->setHasFreeShipping($record->hasFreeShipping);
        $lineItem->setIsPromotable($record->isPromotable);
        $lineItem->setIsShippable($record->isShippable);
        $lineItem->setIsTaxable($record->isTaxable);

        return $lineItem;
    }

    /**
     * Finds (or creates) the {@see LineItemRecord} backing a {@see LineItem} data object, and copies
     * every plain persisted attribute across.
     */
    private function _toRecord(LineItem $lineItem): LineItemRecord
    {
        $record = $lineItem->id
            ? (LineItemRecord::query()->find($lineItem->id) ?? new LineItemRecord())
            : new LineItemRecord();

        $record->type = $lineItem->type;
        $record->orderId = $lineItem->orderId;
        $record->purchasableId = $lineItem->purchasableId;
        $record->lineItemStatusId = $lineItem->lineItemStatusId;
        $record->taxCategoryId = $lineItem->taxCategoryId;
        $record->shippingCategoryId = $lineItem->shippingCategoryId;
        $record->qty = $lineItem->qty;
        $record->note = $lineItem->note;
        $record->privateNote = $lineItem->privateNote;
        $record->weight = $lineItem->weight;
        $record->length = $lineItem->length;
        $record->height = $lineItem->height;
        $record->width = $lineItem->width;
        $record->price = $lineItem->getPrice();
        $record->promotionalPrice = $lineItem->getPromotionalPrice();
        $record->options = $lineItem->getOptions();
        $record->snapshot = $lineItem->getSnapshot();

        return $record;
    }
}
