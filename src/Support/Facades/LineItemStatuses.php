<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Order\Data\LineItemStatus|null getLineItemStatusByHandle(string $handle, int|null $storeId = null)
 * @method static int|null getDefaultLineItemStatusId(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\LineItemStatus|null getDefaultLineItemStatus(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\LineItemStatus|null getDefaultLineItemStatusForLineItem(\CraftCms\Commerce\Order\LineItem\Data\LineItem $lineItem)
 * @method static bool saveLineItemStatus(\CraftCms\Commerce\Order\Data\LineItemStatus $lineItemStatus, bool $runValidation = true)
 * @method static void handleChangedLineItemStatus(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool archiveLineItemStatusById(int $id, int|null $storeId = null)
 * @method static void handleArchivedLineItemStatus(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static \Illuminate\Support\Collection getAllLineItemStatuses(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\LineItemStatus|null getLineItemStatusById(int $id, int|null $storeId = null)
 * @method static bool reorderLineItemStatuses(int[] $ids)
 * @method static void clearCaches()
 *
 * @see \CraftCms\Commerce\Order\LineItemStatuses
 */
class LineItemStatuses extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\LineItemStatuses::class;
    }
}
