<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\models\Discount;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\conditions\IdConditionRule;

/**
 * DiscountTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class DiscountTest extends Unit
{
    /**
     * @dataProvider getPercentDiscountAsPercentDataProvider
     */
    public function testGetPercentDiscountAsPercent($percentDiscount, $expected): void
    {
        $discount = new Discount();
        $discount->percentDiscount = $percentDiscount;

        self::assertSame($expected, $discount->getPercentDiscountAsPercent());
    }

    /**
     * @return array
     */
    public function getPercentDiscountAsPercentDataProvider(): array
    {
        return [
            ['-0.1000', '10%'],
            [0, '0%'],
            [-0.1, '10%'],
            [-0.15, '15%'],
            [-0.105, '10.5%'],
            [-0.10504, '10.504%'],
            ['-0.1050400', '10.504%'],
        ];
    }

    /**
     * @return void
     * @since 4.3.0
     * @dataProvider conditionBuilderDataProvider
     */
    public function testHasOrderCondition(ElementConditionInterface|array|string $orderCondition, bool $expected): void
    {
        if ($orderCondition === 'class' || $orderCondition === 'rules') {
            /** @var OrderCondition $orderCondition */
            $orderCondition = \Craft::$app->getConditions()->createCondition(OrderCondition::class);

            if ($orderCondition === 'rules') {
                $rule = \Craft::$app->getConditions()->createConditionRule([
                    'type' => IdConditionRule::class,
                    'value' => 1,
                ]);
                $orderCondition->addConditionRule($rule);
            }
        }

        $discount = \Craft::createObject([
            'class' => Discount::class,
            'orderCondition' => $orderCondition,
        ]);

        self::assertSame($expected, $discount->hasOrderCondition());
    }

    public function conditionBuilderDataProvider(): array
    {
        return [
            'blank-string' => ['', false],
            'empty-array' => [[], false],
            'no-rules' => ['class', false],
            'rules' => ['rules', true],
        ];
    }
}
