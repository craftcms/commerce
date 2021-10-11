<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use Craft;
use craft\commerce\base\Stat;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\Table as CraftTable;
use yii\db\Expression;

/**
 * Top Products Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopProducts extends Stat
{
    /**
     * Stat returned based on quantity.
     * @since 3.4
     */
    const TYPE_QTY = 'qty';

    /**
     * Stat returned based on revenue.
     * @since 3.4
     */
    const TYPE_REVENUE = 'revenue';

    /**
     * @since 3.4
     */
    const REVENUE_OPTION_DISCOUNT = 'discount';

    /**
     * @since 3.4
     */
    const REVENUE_OPTION_TAX = 'tax';

    /**
     * @since 3.4
     */
    const REVENUE_OPTION_TAX_INCLUDED = 'tax_included';

    /**
     * @since 3.4
     */
    const REVENUE_OPTION_SHIPPING = 'shipping';

    /**
     * @inheritdoc
     */
    protected string $_handle = 'topProducts';

    /**
     * @var string Type either 'qty' or 'revenue'.
     */
    public string $type = self::TYPE_QTY;

    /**
     * @var int Number of products to show.
     */
    public int $limit = 5;

    /**
     * Options to be used when when calculating revenue total.
     *
     * @var string[]
     * @since 3.4
     */
    public array $revenueOptions = [];

    /**
     * Default options for calculating revenue total.
     *
     * @var string[]
     * @since 3.4
     */
    private array $_defaultRevenueOptions = [
        self::REVENUE_OPTION_DISCOUNT,
        self::REVENUE_OPTION_TAX,
        self::REVENUE_OPTION_TAX_INCLUDED,
        self::REVENUE_OPTION_SHIPPING,
    ];

    /**
     * Used for the correct function name `IFNUll` vs `COALESCE` difference between DB engines.
     *
     * @var string
     */
    private string $_ifNullDbFunc;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, string $type = null, $startDate = null, $endDate = null, array $revenueOptions = null)
    {
        $this->_ifNullDbFunc = Craft::$app->getDb()->getIsPgsql() ? 'COALESCE' : 'IFNULL';

        if ($type) {
            $this->type = $type;
        }

        // Set defaults
        $this->revenueOptions = $this->_defaultRevenueOptions;
        if ($revenueOptions !== null && is_array($revenueOptions)) {
            $this->revenueOptions = $revenueOptions;
        }

        parent::__construct($dateRange, $startDate, $endDate);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        $select = [
            '[[v.productId]] as id',
            '[[content.title]]',
            new Expression('SUM([[li.qty]]) as qty'),
            new Expression('SUM([[li.total]]) as revenue'),
            new Expression('SUM([[li.subtotal]]) as revenue_subtotal'),
            $this->getAdjustmentsSelect(),
        ];

        $topProducts = $this->_createStatQuery()
            ->select($select)
            ->leftJoin(Table::LINEITEMS . ' li', '[[li.orderId]] = [[orders.id]]')
            ->leftJoin(Table::PURCHASABLES . ' p', '[[p.id]] = [[li.purchasableId]]')
            ->leftJoin(Table::VARIANTS . ' v', '[[v.id]] = [[p.id]]')
            ->leftJoin(CraftTable::CONTENT . ' content', [
                'and',
                '[[content.elementId]] = [[v.productId]]',
                ['content.siteId' => $primarySite->id],
            ])
            ->leftJoin(['adjustments' => $this->createAdjustmentsSubQuery()], '[[v.productId]] = [[adjustments.productId]]')
            ->groupBy($this->getGroupBy())
            ->orderBy($this->getOrderBy())
            ->andWhere(['not', ['[[v.productId]]' => null]])
            ->limit($this->limit);

        return $topProducts->all();
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        $handle = $this->_handle . $this->type;

        foreach ($this->revenueOptions as $revenueOption) {
            $handle .= '-' . $revenueOption;
        }

        return $handle;
    }

    /**
     * @inheritDoc
     */
    public function prepareData($data)
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                if ($row['id']) {
                    $row['product'] = Plugin::getInstance()->getProducts()->getProductById($row['id']);
                }
            }
        }

        return $data;
    }

    /**
     * Create select statement for a stat type `custom` based on the options chosen.
     *
     * @return Expression
     * @since 3.4
     */
    protected function getAdjustmentsSelect(): Expression
    {
        $select = 'SUM([[li.subtotal]])';

        if (is_array($this->revenueOptions)) {
            if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
                $select .= '+ [[adjustments.discount]]';
            }

            if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
                $select .= '- [[adjustments.tax_included]]';
            }

            if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
                $select .= '- [[adjustments.tax]]';
            }

            if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
                $select .= '- [[adjustments.shipping]]';
            }
        }

        $select = $this->_ifNullDbFunc . '(' . $select . ', SUM([[li.subtotal]]))';

        return new Expression($select . ' as revenue_custom');
    }

    /**
     * Create the adjustments sub query for use with revenue calculation.
     *
     * @return Query
     * @since 3.4
     */
    protected function createAdjustmentsSubQuery(): Query
    {
        $types = [];
        foreach ($this->revenueOptions as $revenueOption) {
            $types[] = strpos($revenueOption, 'tax') === 0 ? 'tax' : $revenueOption;
        }
        $types = array_unique($types);

        return (new Query())
            ->select([
                '[[v.productId]]',
                'discount' => new Expression($this->_ifNullDbFunc . '(SUM(CASE WHEN type=\'discount\' THEN amount END), 0)'),
                'shipping' => new Expression($this->_ifNullDbFunc . '(SUM(CASE WHEN type=\'shipping\' THEN amount END), 0)'),
                'tax' => new Expression($this->_ifNullDbFunc . '(SUM(CASE WHEN type=\'tax\' AND included=false THEN amount END), 0)'),
                'tax_included' => new Expression($this->_ifNullDbFunc . '(SUM(CASE WHEN type=\'tax\' AND included=true THEN amount END), 0)'),
            ])
            ->from(Table::ORDERADJUSTMENTS)
            ->leftJoin(Table::LINEITEMS . ' li', '[[li.id]] = [[lineItemId]]')
            ->leftJoin(Table::VARIANTS . ' v', '[[v.id]] = [[li.purchasableId]]')
            ->where(['not', ['lineItemId' => null]])
            ->andWhere(['not', ['[[v.productId]]' => null]])
            ->andWhere(['type' => $types])
            ->groupBy('[[v.productId]]');
    }

    /**
     * Return the order by clause for the data query.
     *
     * @return Expression
     * @since 3.4
     */
    protected function getOrderBy(): Expression
    {
        if ($this->type === self::TYPE_QTY) {
            return new Expression('SUM([[li.qty]]) DESC');
        }

        // Order by custom revenue options if not all options are selected.
        if ($this->type === self::TYPE_REVENUE && count(array_intersect($this->_defaultRevenueOptions, $this->revenueOptions)) !== count($this->_defaultRevenueOptions)) {
            return new Expression('[[revenue_custom]] DESC');
        }

        return new Expression('SUM([[li.total]]) DESC');
    }

    /**
     * Return group by statement based on state type.
     *
     * @return string
     * @since 3.4
     */
    protected function getGroupBy(): string
    {
        $groupBy = '[[v.productId]], [[content.title]]';

        if (is_array($this->revenueOptions)) {
            if (in_array(self::REVENUE_OPTION_DISCOUNT, $this->revenueOptions, true)) {
                $groupBy .= ', [[adjustments.discount]]';
            }

            if (!in_array(self::REVENUE_OPTION_TAX_INCLUDED, $this->revenueOptions, true)) {
                $groupBy .= ', [[adjustments.tax_included]]';
            }

            if (!in_array(self::REVENUE_OPTION_TAX, $this->revenueOptions, true)) {
                $groupBy .= ', [[adjustments.tax]]';
            }

            if (!in_array(self::REVENUE_OPTION_SHIPPING, $this->revenueOptions, true)) {
                $groupBy .= ', [[adjustments.shipping]]';
            }
        }

        return $groupBy;
    }
}
