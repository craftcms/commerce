<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m171221_120000_subscriptions
 */
class m171221_120000_subscriptions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%commerce_plans}}', [
            'id' => $this->primaryKey(),
            'gatewayId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'reference' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull(),
            'planData' => $this->text(),
            'isArchived' => $this->boolean()->notNull(),
            'dateArchived' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_subscriptions}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'planId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'orderId' => $this->integer(),
            'reference' => $this->string()->notNull(),
            'subscriptionData' => $this->text(),
            'trialDays' => $this->integer()->notNull(),
            'nextPaymentDate' => $this->dateTime(),
            'isCanceled' => $this->boolean()->notNull(),
            'dateCanceled' => $this->dateTime(),
            'isExpired' => $this->boolean()->notNull(),
            'dateExpired' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%commerce_plans}}', 'gatewayId');
        $this->createIndex(null, '{{%commerce_plans}}', 'handle', true);
        $this->createIndex(null, '{{%commerce_plans}}', 'reference');

        $this->createIndex(null, '{{%commerce_subscriptions}}', 'userId');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'planId');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'gatewayId');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'reference', true);
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'nextPaymentDate');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'isCanceled');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'dateCreated');
        $this->createIndex(null, '{{%commerce_subscriptions}}', 'dateExpired');

        $this->addForeignKey(null, '{{%commerce_plans}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'CASCADE');

        $this->addForeignKey(null, '{{%commerce_subscriptions}}', 'userId', '{{%users}}', 'id', 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', 'planId', '{{%commerce_plans}}', 'id', 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'RESTRICT');
        $this->addForeignKey(null, '{{%commerce_subscriptions}}', 'orderId', '{{%commerce_orders}}', 'id', 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171221_120000_subscriptions cannot be reverted.\n";

        return false;
    }
}
