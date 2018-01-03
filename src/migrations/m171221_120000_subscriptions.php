<?php

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
            'response' => $this->text(),
            'isArchived' => $this->boolean()->notNull(),
            'dateArchived' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_subscriptions}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'planId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'reference' => $this->string()->notNull(),
            'trialDays' => $this->integer()->notNull(),
            'nextPaymentDate' => $this->dateTime()->notNull(),
            'paymentAmount' => $this->decimal(14, 4)->defaultValue(0),
            'expiryDate' => $this->dateTime()->notNull(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex($this->db->getIndexName('{{%commerce_plans}}', 'handle', true), '{{%commerce_plans}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_plans}}', 'reference', false), '{{%commerce_plans}}', 'reference', false);

        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'userId', false), '{{%commerce_subscriptions}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'planId', false), '{{%commerce_subscriptions}}', 'planId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'gatewayId', false), '{{%commerce_subscriptions}}', 'gatewayId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'reference', true), '{{%commerce_subscriptions}}', 'reference', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'nextPaymentDate', false), '{{%commerce_subscriptions}}', 'nextPaymentDate', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'paymentAmount', false), '{{%commerce_subscriptions}}', 'paymentAmount', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_subscriptions}}', 'expiryDate', false), '{{%commerce_subscriptions}}', 'expiryDate', false);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_plans}}', 'gatewayId'), '{{%commerce_plans}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'CASCADE', null);

        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_subscriptions}}', 'userId'), '{{%commerce_subscriptions}}', 'userId', '{{%users}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_subscriptions}}', 'planId'), '{{%commerce_subscriptions}}', 'planId', '{{%commerce_plans}}', 'id', 'RESTRICT', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_subscriptions}}', 'gatewayId'), '{{%commerce_subscriptions}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'CASCADE', null);

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
