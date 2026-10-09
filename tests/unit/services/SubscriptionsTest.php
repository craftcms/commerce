<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\base\Plan;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craft\commerce\services\Subscriptions;
use craft\helpers\DateTimeHelper;
use craftcommercetests\fixtures\SubscriptionPlansFixture;
use UnitTester;

/**
 * SubscriptionsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.7
 */
class SubscriptionsTest extends Unit
{
    protected UnitTester $tester;

    protected Subscriptions $service;

    /**
     * @var Subscription[]
     */
    private array $_subscriptions = [];

    public function _fixtures(): array
    {
        return [
            'plans' => ['class' => SubscriptionPlansFixture::class],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param int $expectedCount
     * @dataProvider activeSubscriptionCountDataProvider
     */
    public function testGetActiveSubscriptionCountByPlanId(array $attributes, int $expectedCount): void
    {
        $plan = $this->_getPlan('monthly');
        $this->_createSubscription($plan, $attributes);

        self::assertSame($expectedCount, $this->service->getActiveSubscriptionCountByPlanId($plan->id));
        self::assertSame($expectedCount, $plan->getActiveSubscriptionCount());
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public function activeSubscriptionCountDataProvider(): array
    {
        return [
            'active' => [[], 1],
            'expired' => [['isExpired' => true, 'dateExpired' => DateTimeHelper::now()], 0],
            'suspended' => [['isSuspended' => true, 'dateSuspended' => DateTimeHelper::now()], 0],
            'not-started' => [['hasStarted' => false], 0],
            'canceled-not-expired' => [['isCanceled' => true, 'dateCanceled' => DateTimeHelper::now()], 1],
        ];
    }

    public function testGetActiveSubscriptionCountByPlanIdExcludesTrashedSubscriptions(): void
    {
        $plan = $this->_getPlan('monthly');
        $this->_createSubscription($plan);
        $trashed = $this->_createSubscription($plan);

        Craft::$app->getElements()->deleteElement($trashed);

        self::assertSame(1, $this->service->getActiveSubscriptionCountByPlanId($plan->id));
    }

    public function testGetActiveSubscriptionCountByPlanIdIsScopedToPlan(): void
    {
        $monthlyPlan = $this->_getPlan('monthly');
        $weeklyPlan = $this->_getPlan('weekly-disabled');

        $this->_createSubscription($monthlyPlan);
        $this->_createSubscription($weeklyPlan);
        $this->_createSubscription($weeklyPlan);

        self::assertSame(1, $this->service->getActiveSubscriptionCountByPlanId($monthlyPlan->id));
        self::assertSame(2, $this->service->getActiveSubscriptionCountByPlanId($weeklyPlan->id));
    }

    public function testGetSubscriptionCountByPlanIdIncludesInactiveSubscriptions(): void
    {
        $plan = $this->_getPlan('monthly');
        $this->_createSubscription($plan);
        $this->_createSubscription($plan, ['isExpired' => true, 'dateExpired' => DateTimeHelper::now()]);
        $this->_createSubscription($plan, ['isSuspended' => true, 'dateSuspended' => DateTimeHelper::now()]);
        $this->_createSubscription($plan, ['hasStarted' => false]);

        self::assertSame(4, $this->service->getSubscriptionCountByPlanId($plan->id));
        self::assertSame(4, $plan->getSubscriptionCount());
        self::assertSame(1, $plan->getActiveSubscriptionCount());
    }

    protected function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getSubscriptions();
    }

    protected function _after(): void
    {
        foreach ($this->_subscriptions as $subscription) {
            Craft::$app->getElements()->deleteElementById($subscription->id, Subscription::class, null, true);
        }

        $this->_subscriptions = [];

        parent::_after();
    }

    private function _getPlan(string $key): Plan
    {
        $planId = $this->tester->grabFixture('plans')->getModel($key)->id;

        return Plugin::getInstance()->getPlans()->getPlanById($planId);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function _createSubscription(Plan $plan, array $attributes = []): Subscription
    {
        $subscription = new Subscription();
        $subscription->userId = 1;
        $subscription->planId = $plan->id;
        $subscription->gatewayId = $plan->gatewayId;
        $subscription->reference = 'test-active-count-' . uniqid();
        $subscription->trialDays = 0;
        $subscription->hasStarted = true;
        $subscription->subscriptionData = ['test' => 'active-count'];
        Craft::configure($subscription, $attributes);

        Craft::$app->getElements()->saveElement($subscription, false);
        $this->_subscriptions[] = $subscription;

        return $subscription;
    }
}
