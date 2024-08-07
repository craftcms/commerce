<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m231201_100454_update_discount_base_discount_type migration.
 */
class m231201_100454_update_discount_base_discount_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Disable all the discounts that are using the incorrect `baseDiscountType`
        $this->update(Table::DISCOUNTS, ['enabled' => false], ['not', ['baseDiscountType' => 'value']], updateTimestamp: false);

        // Remove `baseDiscountType` column
        $this->dropColumn(Table::DISCOUNTS, 'baseDiscountType');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231201_100454_update_discount_base_discount_type cannot be reverted.\n";
        return false;
    }
}
