<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\Products;
use CraftCms\Commerce\Database\Table;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Function\Conditional\Coalesce;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Language\CaseGroup;
use Tpetry\QueryExpressions\Language\CaseRule;
use Tpetry\QueryExpressions\Operator\Arithmetic\Add;
use Tpetry\QueryExpressions\Operator\Arithmetic\Subtract;
use Tpetry\QueryExpressions\Operator\Comparison\Equal;
use Tpetry\QueryExpressions\Operator\Logical\CondAnd;
use Tpetry\QueryExpressions\Value\Value;

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

    public function __construct(?string $dateRange = null, ?string $type = null, $startDate = null, $endDate = null, ?array $revenueOptions = null, ?int $storeId = null)
    {
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
            ->addSelect(new Alias(new Sum('li.qty'), 'qty'))
            ->addSelect(new Alias(new Sum('li.total'), 'revenue'))
            ->addSelect(new Alias(new Sum('li.subtotal'), 'revenue_subtotal'))
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
            ->groupBy($this->getGroupBy())
            ->orderBy($this->getOrderBy(), 'desc')
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
    protected function getAdjustmentsSelect(): Expression
    {
        $expression = new Sum('li.subtotal');

        if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
            $expression = new Add($expression, 'adjustments.discount');
        }

        if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
            $expression = new Subtract($expression, 'adjustments.tax_included');
        }

        if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
            $expression = new Subtract($expression, 'adjustments.tax');
        }

        if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
            $expression = new Subtract($expression, 'adjustments.shipping');
        }

        return new Alias(new Coalesce([$expression, new Sum('li.subtotal')]), 'revenue_custom');
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

        $discountAmount = new CaseGroup([
            new CaseRule('amount', new Equal('oa.type', new Value('discount'))),
        ]);
        $shippingAmount = new CaseGroup([
            new CaseRule('amount', new Equal('oa.type', new Value('shipping'))),
        ]);
        $taxAmount = new CaseGroup([
            new CaseRule('amount', new CondAnd(
                new Equal('oa.type', new Value('tax')),
                new Equal('included', new Value(false)),
            )),
        ]);
        $taxIncludedAmount = new CaseGroup([
            new CaseRule('amount', new CondAnd(
                new Equal('oa.type', new Value('tax')),
                new Equal('included', new Value(true)),
            )),
        ]);

        return DB::table(Table::ORDERADJUSTMENTS . ' as oa')
            ->select('v.primaryOwnerId')
            ->addSelect(new Alias(new Coalesce([new Sum($discountAmount), new Value(0)]), 'discount'))
            ->addSelect(new Alias(new Coalesce([new Sum($shippingAmount), new Value(0)]), 'shipping'))
            ->addSelect(new Alias(new Coalesce([new Sum($taxAmount), new Value(0)]), 'tax'))
            ->addSelect(new Alias(new Coalesce([new Sum($taxIncludedAmount), new Value(0)]), 'tax_included'))
            ->leftJoin(Table::LINEITEMS . ' as li', 'li.id', '=', 'oa.lineItemId')
            ->leftJoin(Table::VARIANTS . ' as v', 'v.id', '=', 'li.purchasableId')
            ->whereNotNull('oa.lineItemId')
            ->whereNotNull('v.primaryOwnerId')
            ->whereIn('oa.type', $types)
            ->groupBy('v.primaryOwnerId');
    }

    /**
     * Return the order by expression for the data query.
     */
    protected function getOrderBy(): string|Expression
    {
        if ($this->type === self::TYPE_QTY) {
            return new Sum('li.qty');
        }

        // Order by custom revenue options if not all options are selected.
        if ($this->type === self::TYPE_REVENUE && count(array_intersect($this->_defaultRevenueOptions, $this->revenueOptions)) !== count($this->_defaultRevenueOptions)) {
            return 'revenue_custom';
        }

        return new Sum('li.total');
    }

    /**
     * Return group by columns based on state type.
     *
     * @return string[]
     */
    protected function getGroupBy(): array
    {
        $groupBy = ['v.primaryOwnerId', 'es.title'];

        if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
            $groupBy[] = 'adjustments.discount';
        }

        if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
            $groupBy[] = 'adjustments.tax_included';
        }

        if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
            $groupBy[] = 'adjustments.tax';
        }

        if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
            $groupBy[] = 'adjustments.shipping';
        }

        return $groupBy;
    }
}
