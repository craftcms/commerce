<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180402_161903_primary_customer_addresses_relations migration.
 */
class m180402_161903_primary_customer_addresses_relations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $rougueOnes = (new \craft\db\Query())
            ->select('customers.primaryBillingAddressId')
            ->from('{{%commerce_customers}} customers')
            ->leftJoin('{{%commerce_addresses}} addresses', 'customers.primaryBillingAddressId = addresses.id')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['customers.primaryBillingAddressId' => null]])
            ->column();

        foreach ($rougueOnes as $id) {
            $this->update('{{%commerce_customers}}', ['primaryBillingAddressId' => null], ['primaryBillingAddressId' => $id]);
        }

        $rougueTwos = (new \craft\db\Query())
            ->select('customers.primaryShippingAddressId')
            ->from('{{%commerce_customers}} customers')
            ->leftJoin('{{%commerce_addresses}} addresses', 'customers.primaryShippingAddressId = addresses.id')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['customers.primaryShippingAddressId' => null]])
            ->column();

        foreach ($rougueTwos as $id) {
            $this->update('{{%commerce_customers}}', ['primaryShippingAddressId' => null], ['primaryShippingAddressId' => $id]);
        }

        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryBillingAddressId'], '{{%commerce_addresses}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryShippingAddressId'], '{{%commerce_addresses}}', ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_161903_primary_customer_addresses_relations cannot be reverted.\n";
        return false;
    }
}
