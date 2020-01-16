<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\helpers\DateTimeHelper;
use yii\db\Expression;

/**
 * Total Revenue Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalRevenue extends Stat
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $_handle = 'totalRevenue';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getData()
    {
        return $this->_createChartQuery(
            [
                new Expression('SUM([[total]]) as revenue'),
                new Expression('COUNT([[id]]) as orderCount'),
            ],
            [
                'revenue' => 0,
                'orderCount' => 0,
            ]
        );
    }
}