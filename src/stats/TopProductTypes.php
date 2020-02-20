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
use craft\db\Table as CraftTable;
use yii\db\Expression;

/**
 * Top Product Types Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopProductTypes extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'topProductTypes';

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

        $results = $this->_createStatQuery()
            ->select([
                '[[pt.id]] as id',
                '[[pt.name]]',
                $selectTotalQty,
                $selectTotalRevenue
            ])
            ->leftJoin(Table::LINEITEMS . ' li', '[[li.orderId]] = [[orders.id]]')
            ->leftJoin(Table::PURCHASABLES . ' p', '[[p.id]] = [[li.purchasableId]]')
            ->leftJoin(Table::VARIANTS . ' v', '[[v.id]] = [[p.id]]')
            ->leftJoin(Table::PRODUCTS . ' pr', '[[pr.id]] = [[v.productId]]')
            ->leftJoin(Table::PRODUCTTYPES . ' pt', '[[pt.id]] = [[pr.typeId]]')
            ->leftJoin(CraftTable::CONTENT . ' content', '[[content.elementId]] = [[v.productId]]')
            ->groupBy('[[pt.id]]')
            ->orderBy($this->type == 'revenue' ? $orderByRevenue : $orderByQty)
            ->limit($this->limit);

        return $results->all();
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }

    /**
     * @inheritDoc
     */
    public function prepareData($data)
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                $row['productType'] = ($row['id']) ? Plugin::getInstance()->getProductTypes()->getProductTypeById((int)$row['id']) : null;
            }
        }

        return $data;
    }
}