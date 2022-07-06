<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use _generated\UnitTesterActions;
use Codeception\Test\Unit;
use craft\commerce\base\Plan;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\services\Plans;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\SubscriptionPlansFixture;
use UnitTester;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * StoreTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.5
 */
class PlansTest extends Unit
{
    /**
     * @var UnitTester|UnitTesterActions
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

    /**
     * @return void
     */
    public function testGetAllPlans(): void
    {
        $plans = $this->service->getAllPlans();

        self::assertCount(2, $plans);
        self::assertEquals(['monthlySubscription', 'weeklySubscription'], ArrayHelper::getColumn($plans, 'handle', false));
    }

    /**
     * @return void
     */
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

    /**
     * @return \int[][]
     */
    public function getPlansByGatewayIdDataProvider(): array
    {
        return [
            'dummy-gateway' => [1, 2],
            'non-existent-gateway' => [99, 0],
        ];
    }

    /**
     * @return void
     */
    public function testGetPlanById(): void
    {
        /** @var Plan $monthlyPlan */
        $monthlyPlan = $this->tester->grabFixture('plans', 'monthly');
        $plan = $this->service->getPlanById($monthlyPlan->id);

        self::assertInstanceOf(Plan::class, $plan);
        self::assertEquals($monthlyPlan->name, $plan->name);
    }

    /**
     * @return void
     */
    public function testGetPlanByUid(): void
    {
        /** @var Plan $monthlyPlan */
        $monthlyPlan = $this->tester->grabFixture('plans', 'monthly');
        $plan = $this->service->getPlanByUid($monthlyPlan->uid);

        self::assertInstanceOf(Plan::class, $plan);
        self::assertEquals($monthlyPlan->name, $plan->name);
    }

    /**
     * @return void
     */
    public function testGetPlanByHandle(): void
    {
        $plan = $this->service->getPlanByHandle('monthlySubscription');
        self::assertEquals('Monthly Subscription', $plan->name);

        $plan = $this->service->getPlanByHandle('weeklySubscription');
        self::assertEquals('Weekly Subscription', $plan->name);
    }

    /**
     * @return void
     */
    public function testGetPlanByReference(): void
    {
        $plan = $this->service->getPlanByReference('monthly_sub');
        self::assertEquals('Monthly Subscription', $plan->name);

        $plan = $this->service->getPlanByReference('weekly_sub');
        self::assertEquals('Weekly Subscription', $plan->name);
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
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


    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function testArchivePlanById(): void
    {
        /** @var Plan $monthlyPlan */
        $monthlyPlan = $this->tester->grabFixture('plans', 'monthly');
        $result = $this->service->archivePlanById($monthlyPlan->id);

        self::assertTrue($result);
        $dbRow = (new Query())
            ->from(Table::PLANS)
            ->select(['id', 'name', 'isArchived'])
            ->where(['isArchived' => true])
            ->andWhere(['id' => $monthlyPlan->id])
            ->one();
        self::assertEquals('Monthly Subscription', $dbRow['name']);
        self::assertEquals($monthlyPlan->id, $dbRow['id']);
        self::assertEquals(true, $dbRow['isArchived']);

        $allPlans = $this->service->getAllPlans();
        $allEnabledPlans = $this->service->getAllEnabledPlans();
        self::assertNull(ArrayHelper::firstWhere($allPlans, 'name', $monthlyPlan->name));
        self::assertNull(ArrayHelper::firstWhere($allEnabledPlans, 'name', $monthlyPlan->name));
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReorderPlans(): void
    {
        $plans = ArrayHelper::getColumn($this->service->getAllPlans(), 'id', false);

        $result = $this->service->reorderPlans(array_reverse($plans));
        self::assertTrue($result);
        $dbRows = (new Query())
            ->from(Table::PLANS)
            ->select(['id', 'sortOrder'])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
        $previousSortOrder = -1;
        foreach (array_reverse($plans) as $key => $id) {
            self::assertEquals($id, $dbRows[$key]['id']);
            self::assertGreaterThan($previousSortOrder, $dbRows[$key]['sortOrder']);
            $previousSortOrder = $dbRows[$key]['sortOrder'];
        }
    }

    /**
     *
     */
    public function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getPlans();
    }
}
