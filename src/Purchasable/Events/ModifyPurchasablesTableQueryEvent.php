<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Database\Query\Builder;

/**
 * Dispatched while building the query for the purchasables table an order's "Add a line item" dialog lists.
 *
 * The query selects from `commerce_purchasables` (aliased `purchasables`), joined to `elements`,
 * `elements_sites` (aliased `es`) and the purchasable's store row (aliased `pstores`). It is dispatched
 * after the filters and sort are applied and before the total is counted and the page is taken, so
 * listeners can add joins, conditions or ordering.
 *
 * Listeners must keep selecting `purchasables.id` and `elements.type`, because the page's purchasables are loaded from them.
 *
 * @since 6.0.0
 */
class ModifyPurchasablesTableQueryEvent
{
    public function __construct(
        public Builder $query,
        public ?string $search = null,
        public ?Order $order = null,
    ) {
    }
}
