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
 * Top Products Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopProducts extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'topProducts';

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

        $topProducts = $this->_createStatQuery()
            ->select([
                '[[v.productId]] as id',
                '[[content.title]]',
                $selectTotalQty,
                $selectTotalRevenue
            ])
            ->leftJoin(Table::LINEITEMS . ' li', '[[li.orderId]] = [[orders.id]]')
            ->leftJoin(Table::PURCHASABLES . ' p', '[[p.id]] = [[li.purchasableId]]')
            ->leftJoin(Table::VARIANTS . ' v', '[[v.id]] = [[p.id]]')
            ->leftJoin(CraftTable::CONTENT . ' content', '[[content.elementId]] = [[v.productId]]')
            ->groupBy('[[v.productId]], [[content.title]]')
            ->orderBy($this->type == 'revenue' ? $orderByRevenue : $orderByQty)
            ->andWhere(['not', ['[[v.productId]]' => null]])
            ->limit($this->limit);

        return $topProducts->all();
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
                if ($row['id']) {
                    $row['product'] = Plugin::getInstance()->getProducts()->getProductById($row['id']);
                }
            }
        }

        return $data;
    }
}