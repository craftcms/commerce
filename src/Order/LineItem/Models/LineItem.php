<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\LineItem\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;

/**
 * Thin Eloquent persistence model for the `commerce_lineitems` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Order\LineItem\LineItems}
 * to read/write rows, which are then hydrated into (or persisted from) the rich
 * {@see \CraftCms\Commerce\Order\LineItem\Data\LineItem} object that the rest of the codebase
 * actually works with. Mirrors the `Entry\Data\EntryType` / `Entry\Models\EntryType` split in cms-6.
 */
class LineItem extends BaseModel
{
    #[\Override]
    protected $table = Table::LINEITEMS;

    #[\Override]
    protected $casts = [
        'type' => LineItemType::class,
        'options' => 'array',
        'snapshot' => 'array',
        'hasFreeShipping' => 'boolean',
        'isPromotable' => 'boolean',
        'isShippable' => 'boolean',
        'isTaxable' => 'boolean',
        'qty' => 'integer',
        'orderId' => 'integer',
        'purchasableId' => 'integer',
        'lineItemStatusId' => 'integer',
        'taxCategoryId' => 'integer',
        'shippingCategoryId' => 'integer',
        'weight' => 'float',
        'length' => 'float',
        'height' => 'float',
        'width' => 'float',
        'price' => 'float',
        'promotionalPrice' => 'float',
        'promotionalAmount' => 'float',
        'salePrice' => 'float',
        'subtotal' => 'float',
        'total' => 'float',
    ];
}
