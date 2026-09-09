<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Top Product Types Stat
 */
class TopProductTypes extends Stat
{
    protected string $_handle = 'topProductTypes';

    /**
     * Type either 'qty' or 'revenue'.
     */
    public string $type = 'qty';

    /**
     * Number of product types to show.
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
        $primarySite = Sites::getPrimarySite();
        $viewableProductTypeIds = app(ProductTypes::class)->getViewableProductTypeIds();

        $results = $this->createStatQuery()
            ->select(['pt.id as id', 'pt.name'])
            ->addSelect(new Alias(new Sum('li.qty'), 'qty'))
            ->addSelect(new Alias(new Sum('li.total'), 'revenue'))
            ->leftJoin(Table::LINEITEMS . ' as li', 'li.orderId', '=', 'orders.id')
            ->leftJoin(Table::PURCHASABLES . ' as p', 'p.id', '=', 'li.purchasableId')
            ->leftJoin(Table::VARIANTS . ' as v', 'v.id', '=', 'p.id')
            ->leftJoin(Table::PRODUCTS . ' as pr', 'pr.id', '=', 'v.primaryOwnerId')
            ->leftJoin(Table::PRODUCTTYPES . ' as pt', 'pt.id', '=', 'pr.typeId')
            ->leftJoin(CmsTable::ELEMENTS_SITES . ' as es', function($join) use ($primarySite) {
                $join->on('es.elementId', '=', 'v.primaryOwnerId')
                    ->where('es.siteId', $primarySite->id);
            })
            ->whereNotNull('pt.name')
            ->whereIn('pt.id', $viewableProductTypeIds)
            ->groupBy('pt.id')
            ->orderBy($this->type == 'revenue' ? new Sum('li.total') : new Sum('li.qty'), 'desc')
            ->limit($this->limit);

        return $results->get()->map(fn($row) => (array)$row)->all();
    }

    #[\Override]
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }

    #[\Override]
    public function prepareData($data): mixed
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                $row['productType'] = $row['id'] ? app(ProductTypes::class)->getProductTypeById((int)$row['id']) : null;
            }
        }

        return $data;
    }
}
