<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\elements\conditions\addresses\DiscountAddressCondition;
use craft\commerce\elements\conditions\customers\DiscountCustomerCondition;
use craft\commerce\elements\conditions\orders\DiscountOrderCondition;
use craft\commerce\models\Discount;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\conditions\IdConditionRule;
use yii\base\InvalidConfigException;

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
     * @param ElementConditionInterface|array|string $condition
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @since 4.3.0
     * @dataProvider conditionBuilderDataProvider
     */
    public function testHasOrderCondition(ElementConditionInterface|array|string $condition, bool $expected): void
    {
        if ($condition === 'class' || $condition === 'rules') {
            /** @var DiscountOrderCondition $condition */
            $conditionBuilder = \Craft::$app->getConditions()->createCondition([
                'class' => DiscountOrderCondition::class,
            ]);
            $conditionBuilder->storeId = 1;

            if ($condition === 'rules') {
                $rule = \Craft::$app->getConditions()->createConditionRule([
                    'type' => IdConditionRule::class,
                    'value' => 1,
                ]);
                $conditionBuilder->addConditionRule($rule);
            }

            $condition = $conditionBuilder;
        }

        /** @var Discount $discount */
        $discount = \Craft::createObject([
            'class' => Discount::class,
            'orderCondition' => $condition,
        ]);

        self::assertSame($expected, $discount->hasOrderCondition());
    }

    /**
     * @param ElementConditionInterface|array|string $condition
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @since 4.3.0
     * @dataProvider conditionBuilderDataProvider
     */
    public function testHasCustomerCondition(ElementConditionInterface|array|string $condition, bool $expected): void
    {
        if ($condition === 'class' || $condition === 'rules') {
            /** @var DiscountCustomerCondition $condition */
            $conditionBuilder = \Craft::$app->getConditions()->createCondition(DiscountCustomerCondition::class);

            if ($condition === 'rules') {
                $rule = \Craft::$app->getConditions()->createConditionRule([
                    'type' => IdConditionRule::class,
                    'value' => 1,
                ]);
                $conditionBuilder->addConditionRule($rule);
            }

            $condition = $conditionBuilder;
        }

        /** @var Discount $discount */
        $discount = \Craft::createObject([
            'class' => Discount::class,
            'customerCondition' => $condition,
        ]);

        self::assertSame($expected, $discount->hasCustomerCondition());
    }


    /**
     * @param ElementConditionInterface|array|string $condition
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @since 4.3.0
     * @dataProvider conditionBuilderDataProvider
     */
    public function testHasBillingAddressCondition(ElementConditionInterface|array|string $condition, bool $expected): void
    {
        if ($condition === 'class' || $condition === 'rules') {
            /** @var DiscountAddressCondition $condition */
            $conditionBuilder = \Craft::$app->getConditions()->createCondition(DiscountAddressCondition::class);

            if ($condition === 'rules') {
                $rule = \Craft::$app->getConditions()->createConditionRule([
                    'type' => IdConditionRule::class,
                    'value' => 1,
                ]);
                $conditionBuilder->addConditionRule($rule);
            }

            $condition = $conditionBuilder;
        }

        /** @var Discount $discount */
        $discount = \Craft::createObject([
            'class' => Discount::class,
            'billingAddressCondition' => $condition,
        ]);

        self::assertSame($expected, $discount->hasBillingAddressCondition());
    }

    /**
     * @param ElementConditionInterface|array|string $condition
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @since 4.3.0
     * @dataProvider conditionBuilderDataProvider
     */
    public function testHasShippingAddressCondition(ElementConditionInterface|array|string $condition, bool $expected): void
    {
        if ($condition === 'class' || $condition === 'rules') {
            /** @var DiscountAddressCondition $condition */
            $conditionBuilder = \Craft::$app->getConditions()->createCondition(DiscountAddressCondition::class);

            if ($condition === 'rules') {
                $rule = \Craft::$app->getConditions()->createConditionRule([
                    'type' => IdConditionRule::class,
                    'value' => 1,
                ]);
                $conditionBuilder->addConditionRule($rule);
            }

            $condition = $conditionBuilder;
        }

        /** @var Discount $discount */
        $discount = \Craft::createObject([
            'class' => Discount::class,
            'shippingAddressCondition' => $condition,
        ]);

        self::assertSame($expected, $discount->hasShippingAddressCondition());
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
