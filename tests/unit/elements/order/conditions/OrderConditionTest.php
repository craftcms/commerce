<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\orders\CompletedConditionRule;
use craft\commerce\elements\conditions\orders\CouponCodeConditionRule;
use craft\commerce\elements\conditions\orders\CustomerConditionRule;
use craft\commerce\elements\conditions\orders\DateOrderedConditionRule;
use craft\commerce\elements\conditions\orders\HasPurchasableConditionRule;
use craft\commerce\elements\conditions\orders\ItemSubtotalConditionRule;
use craft\commerce\elements\conditions\orders\ItemTotalConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\conditions\orders\OrderSiteConditionRule;
use craft\commerce\elements\conditions\orders\OrderStatusConditionRule;
use craft\commerce\elements\conditions\orders\PaidConditionRule;
use craft\commerce\elements\conditions\orders\ReferenceConditionRule;
use craft\commerce\elements\conditions\orders\ShippingMethodConditionRule;
use craft\commerce\elements\conditions\orders\TotalConditionRule;
use craft\commerce\elements\conditions\orders\TotalDiscountConditionRule;
use craft\commerce\elements\conditions\orders\TotalPaidConditionRule;
use craft\commerce\elements\conditions\orders\TotalPriceConditionRule;
use craft\commerce\elements\conditions\orders\TotalQtyConditionRule;
use craft\commerce\elements\conditions\orders\TotalTaxConditionRule;
use craft\commerce\elements\Order;
use craftcommercetests\fixtures\OrdersFixture;

/**
 * OrderConditionTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.1
 */
class OrderConditionTest extends Unit
{
    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /**
     * @group Product Condition
     */
    public function testCreateCondition(): void
    {
        self::assertInstanceOf(OrderCondition::class, Order::createCondition());
    }

    /**
     * @group Product Condition
     */
    public function testConditionRuleTypes(): void
    {
        $rules = Order::createCondition()->getSelectableConditionRules();
        $rules = array_keys($rules);

        self::assertContains(DateOrderedConditionRule::class, $rules);
        self::assertContains(CompletedConditionRule::class, $rules);
        self::assertContains(CouponCodeConditionRule::class, $rules);
        self::assertContains(CustomerConditionRule::class, $rules);
        self::assertContains(PaidConditionRule::class, $rules);
        self::assertContains(HasPurchasableConditionRule::class, $rules);
        self::assertContains(ItemSubtotalConditionRule::class, $rules);
        self::assertContains(ItemTotalConditionRule::class, $rules);
        self::assertContains(OrderStatusConditionRule::class, $rules);
        self::assertContains(OrderSiteConditionRule::class, $rules);
        self::assertContains(ReferenceConditionRule::class, $rules);
        self::assertContains(ShippingMethodConditionRule::class, $rules);
        self::assertContains(TotalDiscountConditionRule::class, $rules);
        self::assertContains(TotalPaidConditionRule::class, $rules);
        self::assertContains(TotalPriceConditionRule::class, $rules);
        self::assertContains(TotalQtyConditionRule::class, $rules);
        self::assertContains(TotalTaxConditionRule::class, $rules);
        self::assertContains(TotalConditionRule::class, $rules);
    }
}
