<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

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
        $rougueOnes = (new Query())
            ->select(['customers.primaryBillingAddressId'])
            ->from(['{{%commerce_customers}} customers'])
            ->leftJoin('{{%commerce_addresses}} addresses', '[[customers.primaryBillingAddressId]] = [[addresses.id]]')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['customers.primaryBillingAddressId' => null]])
            ->column();

        $this->update('{{%commerce_customers}}', ['primaryBillingAddressId' => null], ['primaryBillingAddressId' => $rougueOnes]);

        $rougueTwos = (new Query())
            ->select(['customers.primaryShippingAddressId'])
            ->from(['{{%commerce_customers}} customers'])
            ->leftJoin('{{%commerce_addresses}} addresses', '[[customers.primaryShippingAddressId]] = [[addresses.id]]')
            ->where(['addresses.id' => null])
            ->andWhere(['not', ['customers.primaryShippingAddressId' => null]])
            ->column();

        $this->update('{{%commerce_customers}}', ['primaryShippingAddressId' => null], ['primaryShippingAddressId' => $rougueTwos]);

        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryBillingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryShippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
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
