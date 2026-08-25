<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Order\Models\LineItemStatus;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\LineItemStatuses::class)` instead.
 */
class LineItemStatuses extends Component
{
    public const EVENT_DEFAULT_LINE_ITEM_STATUS = \CraftCms\Commerce\Order\LineItemStatuses::EVENT_DEFAULT_LINE_ITEM_STATUS;

    public const CONFIG_STATUSES_KEY = \CraftCms\Commerce\Order\LineItemStatuses::CONFIG_STATUSES_KEY;

    public function getLineItemStatusByHandle(string $handle, ?int $storeId = null): ?LineItemStatus
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getLineItemStatusByHandle($handle, $storeId);
    }

    public function getDefaultLineItemStatusId(?int $storeId = null): ?int
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getDefaultLineItemStatusId($storeId);
    }

    public function getDefaultLineItemStatus(?int $storeId = null): ?LineItemStatus
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getDefaultLineItemStatus($storeId);
    }

    public function getDefaultLineItemStatusForLineItem(LineItem $lineItem): ?LineItemStatus
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getDefaultLineItemStatusForLineItem($lineItem);
    }

    public function saveLineItemStatus(LineItemStatus $lineItemStatus, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->saveLineItemStatus($lineItemStatus, $runValidation);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleChangedLineItemStatus(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\LineItemStatuses::class)->handleChangedLineItemStatus($event);
    }

    /**
     * @throws Throwable
     */
    public function archiveLineItemStatusById(int $id, ?int $storeId = null): bool
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->archiveLineItemStatusById($id, $storeId);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleArchivedLineItemStatus(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\LineItemStatuses::class)->handleArchivedLineItemStatus($event);
    }

    /**
     * @return Collection<int, LineItemStatus>
     */
    public function getAllLineItemStatuses(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getAllLineItemStatuses($storeId);
    }

    public function getLineItemStatusById(int $id, ?int $storeId = null): ?LineItemStatus
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->getLineItemStatusById($id, $storeId);
    }

    /**
     * @param int[] $ids
     */
    public function reorderLineItemStatuses(array $ids): bool
    {
        return app(\CraftCms\Commerce\Order\LineItemStatuses::class)->reorderLineItemStatuses($ids);
    }
}
