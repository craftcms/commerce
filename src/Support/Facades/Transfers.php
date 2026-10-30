<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void handleChangedFieldLayout(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void handleDeletedFieldLayout()
 * @method static \CraftCms\Cms\FieldLayout\FieldLayout getFieldLayout()
 * @method static \CraftCms\Commerce\Transfer\Data\TransferDetail[] getTransferDetailsByTransferId(int $transferId)
 * @method static bool markAsPending(\CraftCms\Commerce\Transfer\Elements\Transfer $transfer)
 * @method static void receive(\CraftCms\Commerce\Transfer\Elements\Transfer $transfer, array $quantities)
 *
 * @see \CraftCms\Commerce\Transfer\Transfers
 */
class Transfers extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Transfer\Transfers::class;
    }
}
