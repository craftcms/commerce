<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\orders\CustomerConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craft\elements\User;
use craftcommercetests\fixtures\OrdersFixture;

/**
 * CustomerConditionRuleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.1
 */
class CustomerConditionRuleTest extends Unit
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
     */
    public function testMatchElementIn(): void
    {
        $user = User::find()->email('customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id]);

        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        self::assertTrue($condition->matchElement($order));
    }

    /**
     * @group Order
     */
    public function testNotMatchElementIn(): void
    {
        $user = User::find()->email('not customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id]);

        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        self::assertFalse($condition->matchElement($order));
    }

    /**
     * @group Order
     */
    public function testMatchElementNotIn(): void
    {
        $user = User::find()->email('not customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id], 'ni');

        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        self::assertTrue($condition->matchElement($order));
    }

    /**
     * @group Order
     */
    public function testNotMatchElementNotIn(): void
    {
        $user = User::find()->email('customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id], 'ni');

        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        self::assertFalse($condition->matchElement($order));
    }

    /**
     * @group Order
     */
    public function testModifyQueryMatch(): void
    {
        $user = User::find()->email('customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id]);

        $orderFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $orderFixture->getElement('completed-new');

        $query = Order::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($order->id, $query->ids());
    }

    /**
     * @group Order
     */
    public function testModifyQueryNotMatch(): void
    {
        $user = User::find()->email('not customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id]);

        $query = Order::find();
        $condition->modifyQuery($query);

        self::assertEmpty($query->ids());
    }

    /**
     * @group Order
     */
    public function testModifyQueryMatchNotIn(): void
    {
        $user = User::find()->email('not customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id], 'ni');

        $orderFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $orderFixture->getElement('completed-new');

        $query = Order::find();
        $condition->modifyQuery($query);

        self::assertContainsEquals($order->id, $query->ids());
    }

    /**
     * @group Order
     */
    public function testModifyQueryNotMatchNotIn(): void
    {
        $user = User::find()->email('customer1@crafttest.com')->one();
        $condition = $this->_createCondition([$user?->id], 'ni');

        $query = Order::find();
        $condition->modifyQuery($query);

        self::assertEmpty($query->ids());
    }

    /**
     * @param array $values
     * @param string|null $operator
     * @return OrderCondition
     */
    private function _createCondition(array $values, ?string $operator = null): OrderCondition
    {
        $condition = Order::createCondition();
        /** @var CustomerConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(CustomerConditionRule::class);
        $rule->values = $values;

        if ($operator) {
            $rule->operator = $operator;
        }

        $condition->addConditionRule($rule);

        return $condition;
    }
}
