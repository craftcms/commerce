<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\services\Plans;
use craft\commerce\services\Store;
use craft\db\Query;
use craft\elements\Address;
use craftcommercetests\fixtures\SubscriptionPlansFixture;
use UnitTester;
use yii\helpers\ArrayHelper;

/**
 * StoreTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.5
 */
class PlansTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Plans
     */
    protected Plans $service;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'plans' => [
                'class' => SubscriptionPlansFixture::class,
            ],
        ];
    }

    public function testGetAllPlans(): void
    {
        $plans = $this->service->getAllPlans();

        self::assertCount(2, $plans);
        self::assertEquals(['monthlySubscription', 'weeklySubscription'], ArrayHelper::getColumn($plans, 'handle', false));
    }

    public function testGetAllEnabledPlans(): void
    {
        $plans = $this->service->getAllEnabledPlans();

        self::assertCount(1, $plans);
        self::assertEquals(['monthlySubscription'], ArrayHelper::getColumn($plans, 'handle', false));
    }

    /**
     * @param int $gatewayId
     * @param int $count
     * @return void
     * @dataProvider getPlansByGatewayIdDataProvider
     */
    public function testGetPlansByGatewayId(int $gatewayId, int $count): void
    {
        $plans = $this->service->getPlansByGatewayId($gatewayId);

        self::assertCount($count, $plans);
    }

    public function getPlansByGatewayIdDataProvider(): array
    {
        return [
            'dummy-gateway' => [1, 2],
            'non-existent-gateway' => [99, 0],
        ];
    }

    // TODO implement tets for `getPlanyById` and `getPlanByUid`

    public function testGetPlanByHandle(): void
    {
        $plan = $this->service->getPlanByHandle('monthlySubscription');
        self::assertEquals('Monthly Subscription', $plan->name);

        $plan = $this->service->getPlanByHandle('weeklySubscription');
        self::assertEquals('Weekly Subscription', $plan->name);
    }

    public function testGetPlanByReference(): void
    {
        $plan = $this->service->getPlanByReference('monthly_sub');
        self::assertEquals('Monthly Subscription', $plan->name);

        $plan = $this->service->getPlanByReference('weekly_sub');
        self::assertEquals('Weekly Subscription', $plan->name);
    }

    public function testSavePlan(): void
    {
        $plan = $this->service->getPlanByHandle('monthlySubscription');

        $plan->name .= ' foo';

        $result = $this->service->savePlan($plan, false);

        self::assertTrue($result);
        self::assertEquals('Monthly Subscription foo', $plan->name);
        $dbRow = (new Query())
            ->from(Table::PLANS)
            ->select(['id', 'name'])
            ->where(['name' => 'Monthly Subscription foo'])
            ->one();
        self::assertEquals('Monthly Subscription foo', $dbRow['name']);
        self::assertEquals($plan->id, $dbRow['id']);
    }

    // TODO implement `archivePlanById` test

    // TODO implement `reorderPlans` test

    /**
     *
     */
    public function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getPlans();
    }
}
