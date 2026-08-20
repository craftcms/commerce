<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_transferdetails` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Transfer\Elements\Transfer} to read/write rows, which are then hydrated into
 * (or persisted from) the business {@see \CraftCms\Commerce\Transfer\Models\TransferDetail}
 * object that the rest of the codebase actually works with.
 */
class TransferDetail extends BaseModel
{
    #[\Override]
    protected $table = Table::TRANSFERDETAILS;

    public $timestamps = false;

    #[\Override]
    protected $casts = [
        'transferId' => 'integer',
        'inventoryItemId' => 'integer',
        'quantity' => 'integer',
        'quantityAccepted' => 'integer',
        'quantityRejected' => 'integer',
    ];
}
