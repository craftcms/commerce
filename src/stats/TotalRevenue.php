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
    /**
     * @inheritdoc
     */
    protected $_handle = 'totalRevenue';

    /**
     * @inheritDoc
     */
    public function getData()
    {
        return $this->_createChartQuery(
            [
                new Expression('SUM([[total]]) as revenue'),
                new Expression('COUNT([[id]]) as count'),
            ],
            [
                'revenue' => 0,
                'count' => 0,
            ]
        );
    }
}