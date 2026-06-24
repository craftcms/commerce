<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210922_133730_add_discount_user_addresses_condition_builders migration.
 */
class m220302_133730_add_discount_user_addresses_condition_builders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_discounts}}', 'customerCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'customerCondition', $this->text()->after('orderCondition'));
        }

        if (!$this->db->columnExists('{{%commerce_discounts}}', 'shippingAddressCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'shippingAddressCondition', $this->text()->after('customerCondition'));
        }

        if (!$this->db->columnExists('{{%commerce_discounts}}', 'billingAddressCondition')) {
            $this->addColumn('{{%commerce_discounts}}', 'billingAddressCondition', $this->text()->after('shippingAddressCondition'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220302_133730_add_discount_user_addresses_condition_builders cannot be reverted.\n";
        return false;
    }
}
