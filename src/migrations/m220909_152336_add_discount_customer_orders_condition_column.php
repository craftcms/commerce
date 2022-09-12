<?php

namespace craft\commerce\migrations;

use craft\commerce\elements\conditions\customers\CustomerOrdersCondition;
use craft\db\Migration;
use craft\helpers\Json;

/**
 * m220909_152336_add_discount_customer_orders_condition_column migration.
 */
class m220909_152336_add_discount_customer_orders_condition_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_discounts}}', 'customerOrdersCondition')) {
            $customerOrdersCondition = new CustomerOrdersCondition();
            $this->addColumn('{{%commerce_discounts}}', 'customerOrdersCondition', $this->text());

            $this->update('{{%commerce_discounts}}', [
                'customerOrdersCondition' => Json::encode($customerOrdersCondition->getConfig()),
            ]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220909_152336_add_discount_customer_orders_condition_column cannot be reverted.\n";
        return false;
    }
}
