<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\helpers\ArrayHelper;
use yii\db\Expression;

/**
 * Repeat Customers Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RepeatCustomers extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'repeatingCustomers';

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $total = (int)$this->_createStatQuery()
            ->select(['customerId'])
            ->groupBy('customerId')
            ->count();

        $repeatRows = $this->_createStatQuery()
            ->select([new Expression('COUNT([[id]])')])
            ->groupBy('customerId')
            ->column();


        $repeat = (int)count(array_filter($repeatRows, static function($row) {
            return $row > 1;
        }));

        $percentage = round($total ? ($repeat / $total) * 100 : 0, 0);

        return compact('total', 'repeat', 'percentage');
    }
}