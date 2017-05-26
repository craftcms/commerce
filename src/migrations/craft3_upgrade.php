<?php

namespace craft\commerce\migrations;

use craft\commerce\elements\Order;
use craft\commerce\fieldtypes\Customer;
use craft\commerce\fieldtypes\Products;
use craft\commerce\widgets\Orders;
use craft\commerce\widgets\Revenue;
use craft\db\Migration;

/**
 * m170206_142126_system_name migration.
 */
class craft3_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {

        $row = (new \craft\db\Query())
            ->select(['id', 'settings'])
            ->from(['{{%plugins}}'])
            ->where(['handle' => 'commerce'])
            ->one();

        // Make sure just in case.
        if ($row !== false) {
            // Update all the Element references
            $this->update('{{%elements}}', [
                'type' => Order::class
            ], ['type' => 'Commerce_Order']);

            $this->update('{{%fields}}', [
                'type' => Customer::class
            ], ['type' => 'Commerce_Customer']);

            $this->update('{{%fields}}', [
                'type' => Products::class
            ], ['type' => 'Commerce_Products']);

            $this->update('{{%widgets}}', [
                'type' => Orders::class
            ], ['type' => 'Commerce_Orders']);

            $this->update('{{%widgets}}', [
                'type' => Revenue::class
            ], ['type' => 'Commerce_Revenue']);
        }

        // TODO locales => sites.

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "craft3_upgrade cannot be reverted.\n";

        return false;
    }
}
