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
 * m180402_161904_order_addresses_relations migration.
 */
class m180402_161904_order_addresses_relations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $rougueOnes = (new Query())
            ->select(['orders.billingAddressId'])
            ->from(['{{%commerce_orders}} orders'])
            ->leftJoin('{{%commerce_addresses}} addresses', '[[orders.billingAddressId]] = [[addresses.id]]')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['orders.billingAddressId' => null]])
            ->column();

        foreach ($rougueOnes as $id) {
            $this->update('{{%commerce_orders}}', ['billingAddressId' => null], ['billingAddressId' => $id]);
        }

        $rougueTwos = (new Query())
            ->select(['orders.shippingAddressId'])
            ->from(['{{%commerce_orders}} orders'])
            ->leftJoin('{{%commerce_addresses}} addresses', '[[orders.shippingAddressId]] = [[addresses.id]]')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['orders.shippingAddressId' => null]])
            ->column();

        foreach ($rougueTwos as $id) {
            $this->update('{{%commerce_orders}}', ['shippingAddressId' => null], ['shippingAddressId' => $id]);
        }

        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orders}}');

        $this->addForeignKey(null, '{{%commerce_orders}}', ['billingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['shippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['customerId'], '{{%commerce_customers}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['orderStatusId'], '{{%commerce_orderstatuses}}', ['id'], null, 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['gatewayId'], '{{%commerce_gateways}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_orders}}', ['paymentSourceId'], '{{%commerce_paymentsources}}', ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_161904_order_addresses_relations cannot be reverted.\n";
        return false;
    }
}
