<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\Products;
use CraftCms\Commerce\Database\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Top Products Stat
 */
class TopProducts extends Stat
{
    /**
     * Stat returned based on quantity.
     */
    public const TYPE_QTY = 'qty';

    /**
     * Stat returned based on revenue.
     */
    public const TYPE_REVENUE = 'revenue';

    public const REVENUE_OPTION_DISCOUNT = 'discount';

    public const REVENUE_OPTION_TAX = 'tax';

    public const REVENUE_OPTION_TAX_INCLUDED = 'tax_included';

    public const REVENUE_OPTION_SHIPPING = 'shipping';

    protected string $_handle = 'topProducts';

    /**
     * Type either 'qty' or 'revenue'.
     */
    public string $type = self::TYPE_QTY;

    /**
     * Number of products to show.
     */
    public int $limit = 5;

    /**
     * Options to be used when calculating revenue total.
     *
     * @var string[]
     */
    public array $revenueOptions = [];

    /**
     * Default options for calculating revenue total.
     *
     * @var string[]
     */
    private array $_defaultRevenueOptions = [
        self::REVENUE_OPTION_DISCOUNT,
        self::REVENUE_OPTION_TAX,
        self::REVENUE_OPTION_TAX_INCLUDED,
        self::REVENUE_OPTION_SHIPPING,
    ];

    /**
     * Used for the correct function name `IFNULL` vs `COALESCE` difference between DB engines.
     */
    private readonly string $_ifNullDbFunc;

    public function __construct(?string $dateRange = null, ?string $type = null, $startDate = null, $endDate = null, ?array $revenueOptions = null, ?int $storeId = null)
    {
        $this->_ifNullDbFunc = DB::connection()->getDriverName() === 'pgsql' ? 'COALESCE' : 'IFNULL';

        if ($type) {
            $this->type = $type;
        }

        // Set defaults
        $this->revenueOptions = $this->_defaultRevenueOptions;
        if (is_array($revenueOptions)) {
            $this->revenueOptions = $revenueOptions;
        }

        parent::__construct($dateRange, $startDate, $endDate, $storeId);
    }

    #[\Override]
    public function getData(): array
    {
        $primarySite = Sites::getPrimarySite();

        $topProducts = $this->createStatQuery()
            ->select(['v.primaryOwnerId as id', 'es.title'])
            ->selectRaw('SUM(li.qty) as qty')
            ->selectRaw('SUM(li.total) as revenue')
            ->selectRaw('SUM(li.subtotal) as revenue_subtotal')
            ->addSelect($this->getAdjustmentsSelect())
            ->leftJoin(Table::LINEITEMS . ' as li', 'li.orderId', '=', 'orders.id')
            ->leftJoin(Table::PURCHASABLES . ' as p', 'p.id', '=', 'li.purchasableId')
            ->leftJoin(Table::VARIANTS . ' as v', 'v.id', '=', 'p.id')
            ->leftJoin(Table::PRODUCTS . ' as pr', 'pr.id', '=', 'v.primaryOwnerId')
            ->leftJoin(Table::PRODUCTTYPES . ' as pt', 'pt.id', '=', 'pr.typeId')
            ->leftJoin(CmsTable::ELEMENTS_SITES . ' as es', function($join) use ($primarySite) {
                $join->on('es.elementId', '=', 'v.primaryOwnerId')
                    ->where('es.siteId', $primarySite->id);
            })
            ->leftJoinSub($this->createAdjustmentsSubQuery(), 'adjustments', 'v.primaryOwnerId', '=', 'adjustments.primaryOwnerId')
            ->groupByRaw($this->getGroupBy())
            ->orderByRaw($this->getOrderBy())
            ->whereNotNull('v.primaryOwnerId')
            ->limit($this->limit);

        return $topProducts->get()->map(fn($row) => (array)$row)->all();
    }

    #[\Override]
    public function getHandle(): string
    {
        $handle = $this->_handle . $this->type;

        foreach ($this->revenueOptions as $revenueOption) {
            $handle .= '-' . $revenueOption;
        }

        return $handle;
    }

    #[\Override]
    public function prepareData($data): mixed
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                if ($row['id']) {
                    $row['product'] = app(Products::class)->getProductById($row['id']);
                }
            }
        }

        return $data;
    }

    /**
     * Create select statement for a stat type `custom` based on the options chosen.
     */
    protected function getAdjustmentsSelect(): \Illuminate\Contracts\Database\Query\Expression
    {
        $select = 'SUM(li.subtotal)';

        if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
            $select .= '+ adjustments.discount';
        }

        if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
            $select .= '- adjustments.tax_included';
        }

        if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
            $select .= '- adjustments.tax';
        }

        if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
            $select .= '- adjustments.shipping';
        }

        $select = $this->_ifNullDbFunc . '(' . $select . ', SUM(li.subtotal))';

        return DB::raw($select . ' as revenue_custom');
    }

    /**
     * Create the adjustments sub query for use with revenue calculation.
     */
    protected function createAdjustmentsSubQuery(): Builder
    {
        $types = [];
        foreach ($this->revenueOptions as $revenueOption) {
            $types[] = str_starts_with($revenueOption, 'tax') ? 'tax' : $revenueOption;
        }
        $types = array_unique($types);

        return DB::table(Table::ORDERADJUSTMENTS . ' as oa')
            ->select('v.primaryOwnerId')
            ->selectRaw($this->_ifNullDbFunc . "(SUM(CASE WHEN oa.type='discount' THEN amount END), 0) as discount")
            ->selectRaw($this->_ifNullDbFunc . "(SUM(CASE WHEN oa.type='shipping' THEN amount END), 0) as shipping")
            ->selectRaw($this->_ifNullDbFunc . "(SUM(CASE WHEN oa.type='tax' AND included=false THEN amount END), 0) as tax")
            ->selectRaw($this->_ifNullDbFunc . "(SUM(CASE WHEN oa.type='tax' AND included=true THEN amount END), 0) as tax_included")
            ->leftJoin(Table::LINEITEMS . ' as li', 'li.id', '=', 'oa.lineItemId')
            ->leftJoin(Table::VARIANTS . ' as v', 'v.id', '=', 'li.purchasableId')
            ->whereNotNull('oa.lineItemId')
            ->whereNotNull('v.primaryOwnerId')
            ->whereIn('oa.type', $types)
            ->groupBy('v.primaryOwnerId');
    }

    /**
     * Return the order by clause for the data query.
     */
    protected function getOrderBy(): string
    {
        if ($this->type === self::TYPE_QTY) {
            return 'SUM(li.qty) DESC';
        }

        // Order by custom revenue options if not all options are selected.
        if ($this->type === self::TYPE_REVENUE && count(array_intersect($this->_defaultRevenueOptions, $this->revenueOptions)) !== count($this->_defaultRevenueOptions)) {
            return 'revenue_custom DESC';
        }

        return 'SUM(li.total) DESC';
    }

    /**
     * Return group by statement based on state type.
     */
    protected function getGroupBy(): string
    {
        $groupBy = 'v.primaryOwnerId, es.title';

        if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
            $groupBy .= ', adjustments.discount';
        }

        if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
            $groupBy .= ', adjustments.tax_included';
        }

        if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
            $groupBy .= ', adjustments.tax';
        }

        if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
            $groupBy .= ', adjustments.shipping';
        }

        return $groupBy;
    }
}
