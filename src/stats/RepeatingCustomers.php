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
 * Repeating Customers Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RepeatingCustomers extends Stat
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
            ->groupBy('customerId')
            ->count();

        $repeatRows = $this->_createStatQuery()
            ->select([new Expression('COUNT([[id]])')])
            ->groupBy('customerId')
            ->column();

        $repeat = (int)count(ArrayHelper::removeValue($repeatRows, '1'));

        $percentage = ($repeat / $total) * 100;

        return compact('total', 'repeat', 'percentage');
    }
}