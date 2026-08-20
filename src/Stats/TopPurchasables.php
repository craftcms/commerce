<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Database\Table;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Top Purchasables Stat
 */
class TopPurchasables extends Stat
{
    protected string $_handle = 'topPurchasables';

    /**
     * Type either 'qty' or 'revenue'.
     */
    public string $type = 'qty';

    /**
     * Number of purchasables to show.
     */
    public int $limit = 5;

    public function __construct(?string $dateRange = null, ?string $type = null, $startDate = null, $endDate = null, ?int $storeId = null)
    {
        $this->type = $type ?? $this->type;

        parent::__construct($dateRange, $startDate, $endDate, $storeId);
    }

    #[\Override]
    public function getData(): array
    {
        $viewableProductTypeIds = app(ProductTypes::class)->getViewableProductTypeIds();

        $topPurchasables = $this->createStatQuery()
            ->select(['li.purchasableId', 'p.description', 'p.sku'])
            ->addSelect(new Alias(new Sum('li.qty'), 'qty'))
            ->addSelect(new Alias(new Sum('li.total'), 'revenue'))
            ->leftJoin(Table::LINEITEMS . ' as li', 'li.orderId', '=', 'orders.id')
            ->leftJoin(Table::PURCHASABLES . ' as p', 'p.id', '=', 'li.purchasableId')
            ->leftJoin(Table::VARIANTS . ' as v', 'v.id', '=', 'p.id')
            ->leftJoin(Table::PRODUCTS . ' as pr', 'pr.id', '=', 'v.primaryOwnerId')
            ->leftJoin(Table::PRODUCTTYPES . ' as pt', 'pt.id', '=', 'pr.typeId')
            ->whereIn('pt.id', $viewableProductTypeIds)
            ->groupBy(['li.purchasableId', 'p.sku', 'p.description'])
            ->orderBy($this->type == 'revenue' ? new Sum('li.total') : new Sum('li.qty'), 'desc')
            ->orderBy('p.sku')
            ->limit($this->limit);

        return $topPurchasables->get()->map(fn($row) => (array)$row)->all();
    }

    #[\Override]
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }
}
