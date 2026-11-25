<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order\conditions;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\orders\LineItemSkuConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * LineItemSkuConditionRuleTest
 *
 * The fixture orders `completed-new` and `completed-new-past` have `hct-white` and `hct-blue` line items,
 * and `completed-shipped` only has a `hct-white` line item.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class LineItemSkuConditionRuleTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

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
     * @dataProvider skuDataProvider
     */
    public function testModifyQuery(string $operator, string $value, array $expectedOrders): void
    {
        $query = Order::find();
        $this->_createCondition($operator, $value)->modifyQuery($query);

        self::assertEqualsCanonicalizing($this->_orderIds($expectedOrders), $query->ids());
    }

    /**
     * @group Order
     * @dataProvider skuDataProvider
     */
    public function testMatchElement(string $operator, string $value, array $expectedOrders): void
    {
        $condition = $this->_createCondition($operator, $value);
        $ordersFixture = $this->tester->grabFixture('orders');

        foreach (['completed-new', 'completed-new-past', 'completed-shipped'] as $key) {
            self::assertSame(
                in_array($key, $expectedOrders, true),
                $condition->matchElement($ordersFixture->getElement($key)),
                $key,
            );
        }
    }

    /**
     * @return array[]
     */
    public function skuDataProvider(): array
    {
        $all = ['completed-new', 'completed-new-past', 'completed-shipped'];
        $withBlue = ['completed-new', 'completed-new-past'];

        return [
            // hct-blue is the second line item, so this fails if only the first one is checked
            'equals-second-line-item' => ['=', 'hct-blue', $withBlue],
            'equals-shared' => ['=', 'hct-white', $all],
            'equals-no-match' => ['=', 'hct', []],
            'begins-with' => ['bw', 'hct-', $all],
            'ends-with' => ['ew', '-blue', $withBlue],
            'contains' => ['**', 'ct-bl', $withBlue],
            'in' => ['in', '["missing","hct-blue"]', $withBlue],
            'empty-value' => ['=', '', $all],
        ];
    }

    private function _orderIds(array $keys): array
    {
        $ordersFixture = $this->tester->grabFixture('orders');

        return array_map(fn(string $key) => $ordersFixture->getElement($key)->id, $keys);
    }

    private function _createCondition(string $operator, string $value): OrderCondition
    {
        $condition = Order::createCondition();
        /** @var LineItemSkuConditionRule $rule */
        $rule = \Craft::$app->getConditions()->createConditionRule(LineItemSkuConditionRule::class);
        $rule->operator = $operator;
        $rule->value = $value;
        $condition->addConditionRule($rule);

        return $condition;
    }
}
