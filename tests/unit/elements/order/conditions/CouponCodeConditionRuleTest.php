<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\orders\CouponCodeConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craftcommercetests\fixtures\OrdersFixture;

/**
 * CouponCodeConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class CouponCodeConditionRuleTest extends Unit
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
     * @group Order
     * @dataProvider matchElementDataProvider
     */
    public function testMatchElement(?string $coupon, string $operator = '=', ?string $orderCoupon = null, bool $expectedMatch = true): void
    {
        $condition = $this->_createCondition($coupon, $operator);

        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        if ($orderCoupon) {
            $order->couponCode = $orderCoupon;
        }

        $match = $condition->matchElement($order);

        if ($expectedMatch) {
            self::assertTrue($match);
        } else {
            self::assertFalse($match);
        }
    }

    /**
     * @return array[]
     */
    public function matchElementDataProvider(): array
    {
        return [
            'match-equals' => ['coupon1', '=', 'coupon1', true],
            'match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN1', true],
            'no-match-equals' => ['coupon1', '=', 'coupon2', false],
            'no-match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN2', false],
            'no-match-equals-null' => ['coupon1', '=', null, false],
            'match-contains' => ['coupon1', '**', 'coupon1', true],
            'match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN1', true],
            'no-match-contains' => ['coupon1', '**', 'coupon2', false],
            'no-match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN2', false],
            'match-begins-with' => ['coupon', 'bw', 'coupon1', true],
            'match-begins-with-case-insensitive' => ['coupon', 'bw', 'cOuPoN1', true],
            'no-match-begins-with' => ['coupon', 'bw', 'foocoupon2', false],
            'no-match-begins-with-case-insensitive' => ['coupon', 'bw', 'foocOuPoN2', false],
            'match-ends-with' => ['pon1', 'ew', 'coupon1', true],
            'match-ends-with-case-insensitive' => ['pon1', 'ew', 'cOuPoN1', true],
            'no-match-ends-with' => ['pon2', 'ew', 'coupon2foo', false],
            'no-match-ends-with-case-insensitive' => ['pon2', 'ew', 'cOuPoN2foo', false],
        ];
    }

    /**
     * @group Order
     * @dataProvider modifyQueryDataProvider
     */
    public function testModifyQuery(?string $coupon, string $operator = '=', ?string $orderCoupon = null, int $expectedResults = 0): void
    {
        $condition = $this->_createCondition($coupon, $operator);
        $orderFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $orderFixture->getElement('completed-new');

        // Temporarily add a coupon code to an order
        \craft\commerce\records\Order::updateAll(['couponCode' => $orderCoupon], ['id' => $order->id]);

        $query = Order::find();
        $condition->modifyQuery($query);

        self::assertCount($expectedResults, $query->ids());

        if ($expectedResults > 0) {
            self::assertContainsEquals($order->id, $query->ids());
        } else {
            self::assertEmpty($query->ids());
        }

        // Remove temporary coupon code
        \craft\commerce\records\Order::updateAll(['couponCode' => null], ['id' => $order->id]);
    }

    /**
     * @return array[]
     */
    public function modifyQueryDataProvider(): array
    {
        return [
            'match-equals' => ['coupon1', '=', 'coupon1', 1],
            'match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN1', 1],
            'no-match-equals' => ['coupon1', '=', 'coupon2', 0],
            'no-match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN2', 0],
            'no-match-equals-null' => ['coupon1', '=', null, 0],
            'match-contains' => ['coupon1', '**', 'coupon1', 1],
            'match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN1', 1],
            'no-match-contains' => ['coupon1', '**', 'coupon2', 0],
            'no-match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN2', 0],
            'match-begins-with' => ['coupon', 'bw', 'coupon1', 1],
            'match-begins-with-case-insensitive' => ['coupon', 'bw', 'cOuPoN1', 1],
            'no-match-begins-with' => ['coupon', 'bw', 'foocoupon2', 0],
            'no-match-begins-with-case-insensitive' => ['coupon', 'bw', 'foocOuPoN2', 0],
            'match-ends-with' => ['pon1', 'ew', 'coupon1', 1],
            'match-ends-with-case-insensitive' => ['pon1', 'ew', 'cOuPoN1', 1],
            'no-match-ends-with' => ['pon2', 'ew', 'coupon2foo', 0],
            'no-match-ends-with-case-insensitive' => ['pon2', 'ew', 'cOuPoN2foo', 0],
        ];
    }

    /**
     * @param string|null $value
     * @param string|null $operator
     * @return OrderCondition
     */
    private function _createCondition(?string $value, ?string $operator = null): OrderCondition
    {
        $condition = Order::createCondition();
        /** @var CouponCodeConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(CouponCodeConditionRule::class);
        $rule->value = $value;

        if ($operator) {
            $rule->operator = $operator;
        }

        $condition->addConditionRule($rule);

        return $condition;
    }
}
