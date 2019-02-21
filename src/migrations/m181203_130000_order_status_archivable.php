<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * m181203_130000_order_status_archivable migration.
 */
class m181203_130000_order_status_archivable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Add new columns
        $this->addColumn('{{%commerce_orderstatuses}}', 'isArchived', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%commerce_orderstatuses}}', 'dateArchived', $this->dateTime());

        // Create the index
        $this->createIndex(null, '{{%commerce_orderstatuses}}', 'isArchived', false);

        // Drop ALL THE FKs!!!
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderhistories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orders}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderstatus_emails}}', $this);

        // For people that never had order history cascade deletes when an order was deleted.
        $orderIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_orders}}'])
            ->column();

        $brokenOrderHistoryIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_orderhistories}}'])
            ->where(['not', ['orderId' => $orderIds]])
            ->column();

        // Let's delete any order history for orders that don't exist
        $this->delete('{{%commerce_orderhistories}}', ['id' => $brokenOrderHistoryIds]);

        // Rebuild all the foreign keys.
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['newStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['prevStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');

        $this->addForeignKey(null, '{{%commerce_orders}}', ['billingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['orderStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['paymentSourceId'], '{{%commerce_paymentsources}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['shippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');

        $this->addForeignKey(null, '{{%commerce_orderstatus_emails}}', ['emailId'], '{{%commerce_emails}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orderstatus_emails}}', ['orderStatusId'], '{{%commerce_orderstatuses}}', ['id'], 'RESTRICT', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181203_130000_order_status_archivable cannot be reverted.\n";
        return false;
    }
}
