<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_purchasables_stores` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Purchasable\Elements\Purchasable} and
 * {@see \CraftCms\Commerce\Purchasable\Elements\Donation} to read/write rows, which are then
 * hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Purchasable\Models\PurchasableStore} object that the rest of the
 * codebase actually works with.
 *
 * The legacy `craft\commerce\records\PurchasableStore` is deleted — despite `use`ing
 * `StoreRecordTrait` (shared with several other still-legacy records), it had no consumer of
 * its own left anywhere in the codebase once `Purchasable`/`Donation` were repointed here.
 */
class PurchasableStore extends BaseModel
{
    #[\Override]
    protected $table = Table::PURCHASABLES_STORES;

    #[\Override]
    protected $casts = [
        'purchasableId' => 'integer',
        'storeId' => 'integer',
        'basePrice' => 'float',
        'basePromotionalPrice' => 'float',
        'stock' => 'integer',
        'inventoryTracked' => 'boolean',
        'allowOutOfStockPurchases' => 'boolean',
        'minQty' => 'integer',
        'maxQty' => 'integer',
        'promotable' => 'boolean',
        'availableForPurchase' => 'boolean',
        'freeShipping' => 'boolean',
        'shippingCategoryId' => 'integer',
    ];
}
