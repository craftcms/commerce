<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craft\helpers\DateTimeHelper;
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
            'subscriptions' => [
                'class' => SubscriptionsFixture::class,
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