<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use yii\db\Expression;

/**
 * Top Purchasables Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopPurchasables extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'topPurchasables';

    /**
     * @var string Type either 'qty' or 'revenue'.
     */
    public $type = 'qty';

    /**
     * @var int Number of customers to show.
     */
    public $limit = 5;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, $type = null, $startDate = null, $endDate = null)
    {
        if ($type) {
            $this->type = $type;
        }

        parent::__construct($dateRange, $startDate, $endDate);
    }
    /**
     * @inheritDoc
     */
    public function getData()
    {
        $selectTotalQty = new Expression('SUM([[li.qty]]) as qty');
        $orderByQty = new Expression('SUM([[li.qty]]) DESC');
        $selectTotalRevenue = new Expression('SUM([[li.total]]) as revenue');
        $orderByRevenue = new Expression('SUM([[li.total]]) DESC');

        $topPurchasables = $this->_createStatQuery()
            ->select([
                '[[li.purchasableId]]',
                '[[p.description]]',
                '[[p.sku]]',
                $selectTotalQty,
                $selectTotalRevenue
            ])
            ->leftJoin(Table::LINEITEMS . ' li', '[[li.orderId]] = [[orders.id]]')
            ->leftJoin(Table::PURCHASABLES . ' p', '[[p.id]] = [[li.purchasableId]]')
            ->groupBy('[[li.purchasableId]], [[p.sku]], [[p.description]]')
            ->orderBy($this->type == 'revenue' ? $orderByRevenue : $orderByQty)
            ->limit($this->limit);

        return $topPurchasables->all();
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }
}