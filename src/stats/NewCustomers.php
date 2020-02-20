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
class NewCustomers extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'newCustomers';

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $query = $this->_createStatQuery();
        $query->select([new Expression('COUNT(DISTINCT [[customerId]]) as newCustomers')]);

        return $query->scalar();
    }
}