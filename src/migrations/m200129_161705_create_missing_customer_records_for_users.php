<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;

/**
 * m200129_161705_create_missing_customer_records_for_users migration.
 */
class m200129_161705_create_missing_customer_records_for_users extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $noCustomerUsers = (new Query())
            ->select('users.id as userId')
            ->from('{{%users}} users')
            ->leftJoin('{{%commerce_customers}} customers', '[[customers.userId]] = [[users.id]]')
            ->where(['userId' => null]);

        foreach ($noCustomerUsers->batch(500) as $i => $users) {
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [$user['userId']];
            }
            $this->batchInsert('{{%commerce_customers}}', ['userId'], $rows);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200129_161705_create_missing_customer_records_for_users cannot be reverted.\n";
        return false;
    }
}
