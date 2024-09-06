<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240906_105809_update_existing_coupon_discounts migration.
 */
class m240906_105809_update_existing_coupon_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $couponDiscountIds = (new Query())
            ->from('{{%commerce_coupons}}')
            ->select(['discountId'])
            ->groupBy('discountId');

        $this->update('{{%commerce_discounts}}', ['requireCouponCode' => true], ['id' => $couponDiscountIds]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240906_105809_update_existing_coupon_discounts cannot be reverted.\n";
        return false;
    }
}
