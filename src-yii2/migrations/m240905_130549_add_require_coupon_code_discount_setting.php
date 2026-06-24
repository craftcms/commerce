<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240905_130549_add_require_coupon_code_discount_setting migration.
 */
class m240905_130549_add_require_coupon_code_discount_setting extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_discounts}}', 'requireCouponCode', $this->boolean()->notNull()->defaultValue(false)->after('billingAddressCondition'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240905_130549_add_require_coupon_code_discount_setting cannot be reverted.\n";
        return false;
    }
}
