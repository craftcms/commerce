<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\OrdersFixture;
use craftcommercetests\fixtures\SubscriptionsFixture;
use DateTime;
use DateTimeZone;
use UnitTester;
use yii\base\InvalidConfigException;

/**
 * SubscriptionTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.4
 */
class SubscriptionTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
            'subscriptions' => [
                'class' => SubscriptionsFixture::class,
            ],
        ];
    }

    /**
     * @param array $attributes
     * @param Order|null $order
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getOrderDataProvider
     */
    public function testGetOrder(?string $orderFixtureHandle): void
    {
        $subscription = Craft::createObject(Subscription::class);

        if ($orderFixtureHandle) {
            $orderFixture = $this->tester->grabFixture('orders')->getElement($orderFixtureHandle);
            $subscription->orderId = $orderFixture->id;
            $order = Plugin::getInstance()->getOrders()->getOrderById($orderFixture->id);

            self::assertEquals($order->toArray(), $subscription->getOrder()->toArray());
        } else {
            self::assertEquals(null, $subscription->getOrder());
        }
    }

    /**
     * @return array
     */
    public function getOrderDataProvider(): array
    {
        return [
            'no-order' => [
                null,
            ],
            'order' => [
                'completed-new',
            ],
        ];
    }

    /**
     * @param array $attributes
     * @param string $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getGatewayDataProvider
     */
    public function testGetGateway(array $attributes, ?string $expected): void
    {
        $subscription = Craft::createObject(Subscription::class, [
            'config' => [
                'attributes' => $attributes,
            ],
        ]);

        if ($expected === null) {
            self::assertNull($subscription->getGateway());
        } else {
            self::assertEquals($expected, $subscription->getGateway()->handle);
        }
    }

    /**
     * @return array[]
     */
    public function getGatewayDataProvider(): array
    {
        return [
            'no-gateway' => [
                [],
                null,
            ],
            'gateway' => [
                ['gatewayId' => 1],
                'dummy',
            ],
        ];
    }

    /**
     * @param array $attributes
     * @param array|null $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getAlternativePlansDataProvider
     */
    public function testGetAlternativePlans(array $attributes, ?array $expected): void
    {
        $subscription = Craft::createObject(Subscription::class, [
            'config' => [
                'attributes' => $attributes,
            ],
        ]);

        if ($expected === null) {
            self::assertNull($subscription->getAlternativePlans());
        } else {
            self::assertEquals($expected, $subscription->getAlternativePlans());
        }
    }

    /**
     * @return array[]
     */
    public function getAlternativePlansDataProvider(): array
    {
        return [
            'no-gateway' => [
                [],
                [],
            ],
            'gateway' => [
                ['gatewayId' => 1],
                [],
            ],
        ];
    }

    /**
     * @param array $attributes
     * @param bool $expected
     * @return void
     * @throws InvalidConfigException
     * @dataProvider getIsOnTrialDataProvider
     */
    public function testGetIsOnTrial(array $attributes, bool $expected): void
    {
        $subscription = Craft::createObject(Subscription::class, [
            'config' => [
                'attributes' => $attributes,
            ],
        ]);

        self::assertEquals($subscription->getIsOnTrial(), $expected);
    }

    /**
     * @return array[]
     * @throws \Exception
     */
    public function getIsOnTrialDataProvider(): array
    {
        return [
            'no-attributes' => [
                [],
                false,
            ],
            'on-trial' => [
                ['trialDays' => 10],
                false,
            ],
            'expired' => [
                [
                    'isExpired' => true,
                    'dataExpired' => (new DateTime('yesterday', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                    'trialDays' => 10,
                ],
                false,
            ],
        ];
    }
}
