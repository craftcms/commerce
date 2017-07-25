<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;

/**
 * m170721_150000_paymentmethod_type_changes
 */
class m170725_130000_paymentmethods_are_now_gateways extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        MigrationHelper::dropForeignKeyIfExists('{{%commerce_orders}}', ['paymentMethodId'], $this);
        MigrationHelper::dropForeignKeyIfExists('{{%commerce_transactions}}', ['paymentMethodId'], $this);
        MigrationHelper::dropIndexIfExists('{{%commerce_orders}}', 'paymentMethodId', false, $this);
        MigrationHelper::dropIndexIfExists('{{%commerce_transactions}}', 'paymentMethodId', false, $this);
        MigrationHelper::dropIndexIfExists('{{%commerce_paymentmethods}}', 'name', true, $this);

        $this->renameTable('{{%commerce_paymentmethods}}', '{{%commerce_gateways}}');
        $this->addColumn('{{%commerce_gateways}}', 'handle', $this->string()->notNull());

        $rows = (new Query())
            ->select(['id', 'name', 'type'])
            ->from('{{%commerce_gateways}}')
            ->all();

        foreach ($rows as $row) {
            $handle = StringHelper::toCamelCase(StringHelper::toAscii($row['name']));
            $type = preg_replace('/\\\\paymentmethods\\\\/i', '\\\\gateways\\\\', $row['type']);
            $this->update('{{%commerce_gateways}}', ['handle' => $handle, 'type' => $type], [ 'id' => $row['id']]);
        }

        $this->renameColumn('{{%commerce_orders}}', 'paymentMethodId', 'gatewayId');
        $this->renameColumn('{{%commerce_transactions}}', 'paymentMethodId', 'gatewayId');

        $this->createIndex($this->db->getIndexName('{{%commerce_gateways}}', 'name', true), '{{%commerce_gateways}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_gateways}}', 'handle', true), '{{%commerce_gateways}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'gatewayId', false), '{{%commerce_orders}}', 'gatewayId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_transactions}}', 'gatewayId', false), '{{%commerce_transactions}}', 'gatewayId', false);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'gatewayId'), '{{%commerce_orders}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_transactions}}', 'gatewayId'), '{{%commerce_transactions}}', 'gatewayId', '{{%commerce_gateways}}', 'id', null, 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170721_150000_paymentmethod_type_changes cannot be reverted.\n";

        return false;
    }
}
