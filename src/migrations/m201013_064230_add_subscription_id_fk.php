<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Subscription;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * m201013_064230_add_subscription_id_fk migration.
 */
class m201013_064230_add_subscription_id_fk extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $ids = (new Query())
            ->select(['id'])
            ->from('{{%elements}}')
            ->where(['type' => Subscription::class])
            ->column();

        $orphanedSubs = (new Query())
            ->select(['id'])
            ->from('{{%commerce_subscriptions}}')
            ->where(['not', ['id' => $ids]])
            ->column();

        $this->delete('{{%commerce_subscriptions}}', ['id' => $orphanedSubs]);

        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_subscriptions}}', $this);

        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['userId'], '{{%users}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['planId'], '{{%commerce_plans}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'SET NULL');

        return true;

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201013_064230_add_subscription_id_fk cannot be reverted.\n";
        return false;
    }
}
