<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use yii\db\Expression;

/**
 * Total Orders Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrders extends Stat
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $_handle = 'totalOrders';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $query = $this->_createStatQuery();
        $query->select([new Expression('COUNT([[id]]) as totalOrders')]);
        $chartData = $this->_createChartQuery([
            new Expression('COUNT([[id]]) as totalOrders'),
        ], [
            'totalOrders' => 0,
        ]);

        return [
            'total' => $query->scalar(),
            'chart' => $chartData,
        ];
    }
}