<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\Db;
use yii\db\Expression;

/**
 * New Customers Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class NewCustomers extends Stat
{
    /**
     * @inheritdoc
     */
    protected string $_handle = 'newCustomers';

    /**
     * @inheritDoc
     */
    public function getData(): string|int|bool|null
    {
        $query = $this->_createStatQuery();

        // Subquery to find customers who have orders before the start date
        $existingCustomersQuery = new Query()
            ->select(['customerId'])
            ->from(Table::ORDERS)
            ->where(['isCompleted' => true])
            ->andWhere(['not', ['customerId' => null]])
            ->andWhere(['<', 'dateOrdered', Db::prepareDateForDb($this->getStartDate())]);

        $query->select([new Expression('COUNT(DISTINCT [[customerId]]) as newCustomers')])
            ->andWhere(['not', ['customerId' => null]])
            ->andWhere(['not in', 'customerId', $existingCustomersQuery]);

        return $query->scalar();
    }
}
