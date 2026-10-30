<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries;

use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * A generic, cross-purchasable-type element query. {@see Purchasable} is abstract with no
 * concrete table of its own, so {@see Purchasable::find()} returns a plain {@see ElementQuery}
 * spanning only `elements`/`elements_sites`. This adds the joins a purchasable condition
 * typically needs to filter against:
 *
 *  - `commerce_purchasables`, so condition rules written against a concrete purchasable column
 *    (e.g. `SkuConditionRule`, via `PurchasableQuery::applySku()`) resolve correctly even though
 *    this query's own FROM is `elements`.
 *  - `commerce_site_stores`/`commerce_purchasables_stores`, for store-scoped filters like
 *    `purchasables_stores.promotable`.
 *
 * {@see initQueriesSites()} is overridden because {@see Purchasable::isLocalized()} is `false`,
 * so the inherited `QueriesSites` concern would otherwise force an explicitly-set `siteId` back
 * to the primary site before every query. If `siteId()` is never called on this query, no site
 * filter is applied at all.
 *
 * @extends ElementQuery<Purchasable>
 */
class PurchasableConditionQuery extends ElementQuery
{
    /** @param array<string, mixed> $config */
    public function __construct(string $elementType, array $config = [])
    {
        parent::__construct($elementType, $config);

        $this->query->leftJoin(Table::PURCHASABLES, 'commerce_purchasables.id', '=', 'elements.id');
        $this->query->leftJoin(new Alias(Table::SITESTORES, 'sitestores'), 'elements_sites.siteId', '=', 'sitestores.siteId');
        $this->query->leftJoin(new Alias(Table::PURCHASABLES_STORES, 'purchasables_stores'), function($join) {
            $join->on('purchasables_stores.storeId', '=', 'sitestores.storeId')
                ->on('purchasables_stores.purchasableId', '=', 'elements.id');
        });
    }

    #[Override]
    protected function initQueriesSites(): void
    {
        $this->beforeQuery(static function(ElementQuery $elementQuery) {
            ElementQuery::applySiteId($elementQuery, $elementQuery->siteId);
        });
    }
}
