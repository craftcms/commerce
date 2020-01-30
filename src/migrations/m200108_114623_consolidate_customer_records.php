<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\queue\jobs\ConsolidateGuestOrders;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Expression;

/**
 * m200108_114623_consolidate_customer_records migration.
 */
class m200108_114623_consolidate_customer_records extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $customers = (new Query())
            ->select([
                'email',
                new Expression('COUNT(DISTINCT [[customerId]]) as customerIdCount')
            ])
            ->from('{{%commerce_orders}}')
            ->where(['isCompleted' => true])
            ->groupBy('[[email]]')
            ->having(new Expression('COUNT(DISTINCT [[customerId]]) > 1'))
            ->orderBy(new Expression('COUNT(DISTINCT [[customerId]]) DESC'))
            ->all();

        if (!empty($customers)) {
            foreach ($customers as $customer) {
                Craft::$app->getQueue()->push(new ConsolidateGuestOrders([
                    'emails' => [$customer['email']]
                ]));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200108_114623_consolidate_customer_records cannot be reverted.\n";
        return false;
    }
}
