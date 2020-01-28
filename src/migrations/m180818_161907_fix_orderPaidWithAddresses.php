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
 * m180818_161907_fix_orderPaidWithAddresses migration.
 */
class m180818_161907_fix_orderPaidWithAddresses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Need to loop over every order that does not have a date paid
        $orderIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_orders}}'])
            ->where(['datePaid' => null])
            ->andWhere('[[totalPaid]] >= [[totalPrice]]')
            ->column();

        foreach ($orderIds as $id) {
            $recentSuccessfulTransactionDate = (new Query())
                ->select(['dateUpdated'])
                ->from(['{{%commerce_transactions}}'])
                ->where([
                    'orderId' => $id,
                    'status' => 'success',
                    'type' => ['purchase', 'capture']
                ])
                ->orderBy(['dateUpdated' => SORT_DESC])
                ->scalar();

            if ($recentSuccessfulTransactionDate) {
                $this->update('{{%commerce_orders}}', ['datePaid' => $recentSuccessfulTransactionDate, 'paidStatus' => 'paid'], ['id' => $id]);
            }
        }

        // Fix shipping and billing addresses that were not cloned correctly on order complete.

        // Fix Shipping Address ID

        // Get all order address IDs for completed orders where the address is still in the customers address book
        $badAddresses = (new Query())
            ->select(['[[orders.id]] AS orderId', '[[orders.shippingAddressId]]', '[[customerAddresses.addressId]]'])
            ->from(['{{%commerce_orders}} orders'])
            ->where(['orders.isCompleted' => true])
            ->andWhere('[[customerAddresses.addressId]] IS NOT NULL')
            ->leftJoin('{{%commerce_customers_addresses}} customerAddresses', '[[customerAddresses.addressId]] = [[orders.shippingAddressId]]')
            ->all();

        foreach ($badAddresses as $badAddress) {
            $address = (new Query())
                ->select('*')
                ->from(['{{%commerce_addresses}}'])
                ->where(['id' => $badAddress['shippingAddressId']])
                ->one();

            // drop the ID so we can duplicate the address
            unset($address['id']);

            $this->insert('{{%commerce_addresses}}', $address);
            $newAddressId = $this->db->getLastInsertID('{{%commerce_addresses}}');
            $this->update('{{%commerce_orders}}', ['shippingAddressId' => $newAddressId], ['id' => $badAddress['orderId']]);
        }

        // Fix Billing Address ID

        // Get all order address IDs for completed orders where the address is still in the customers address book
        $badAddresses = (new Query())
            ->select(['[[orders.id]] AS orderId', '[[orders.billingAddressId]]', '[[customerAddresses.addressId]]'])
            ->from(['{{%commerce_orders}} orders'])
            ->where(['orders.isCompleted' => true])
            ->andWhere('[[customerAddresses.addressId]] IS NOT NULL')
            ->leftJoin('{{%commerce_customers_addresses}} customerAddresses', '[[customerAddresses.addressId]] = [[orders.billingAddressId]]')
            ->all();

        foreach ($badAddresses as $badAddress) {
            $address = (new Query())
                ->select('*')
                ->from(['{{%commerce_addresses}}'])
                ->where(['id' => $badAddress['billingAddressId']])
                ->one();

            // drop the ID so we can duplicate the address
            unset($address['id']);

            $this->insert('{{%commerce_addresses}}', $address);
            $newAddressId = $this->db->getLastInsertID('{{%commerce_addresses}}');
            $this->update('{{%commerce_orders}}', ['billingAddressId' => $newAddressId], ['id' => $badAddress['orderId']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180818_161907_fix_orderPaidWithAddresses cannot be reverted.\n";
        return false;
    }
}