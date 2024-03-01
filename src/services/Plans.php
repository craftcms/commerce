<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\db\Table;
use craft\commerce\events\PlanEvent;
use craft\commerce\Plugin;
use craft\commerce\records\Plan as PlanRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Plans service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property array|Plan[] $allEnabledPlans
 * @property array|Plan[] $allPlans
 */
class Plans extends Component
{
    /**
     * @event PlanEvent The event that is triggered when a plan is archived.
     *
     * Plugins can get notified whenever a subscription plan is being archived.
     * This is useful as sometimes this can be triggered by an action on the gateway.
     *
     * ```php
     * use craft\commerce\events\PlanEvent;
     * use craft\commerce\services\Plans;
     * use yii\base\Event;
     *
     * Event::on(Plans::class, Plans::EVENT_ARCHIVE_PLAN, function(PlanEvent $e) {
     *     // Do something as the plan is being retired.
     * });
     * ```
     */
    public const EVENT_ARCHIVE_PLAN = 'archivePlan';

    /**
     * @event PlanEvent The event that is triggered before a plan is saved.
     *
     * Plugins can get notified before a subscription plan is being saved.
     *
     * ```php
     * use craft\commerce\events\PlanEvent;
     * use craft\commerce\services\Plans;
     * use yii\base\Event;
     *
     * Event::on(Plans::class, Plans::EVENT_BEFORE_SAVE_PLAN, function(PlanEvent $e) {
     *     // Do something
     * });
     * ```
     */
    public const EVENT_BEFORE_SAVE_PLAN = 'beforeSavePlan';

    /**
     * @event PlanEvent The event that is triggered after a plan is saved.
     *
     * Plugins can get notified after a subscription plan is being saved.
     *
     * ```php
     * use craft\commerce\events\PlanEvent;
     * use craft\commerce\services\Plans;
     * use yii\base\Event;
     *
     * Event::on(Plans::class, Plans::EVENT_AFTER_SAVE_PLAN, function(PlanEvent $e) {
     *     // Do something
     * });
     * ```
     */
    public const EVENT_AFTER_SAVE_PLAN = 'afterSavePlan';

    /**
     * Memoized array of plans.
     *
     * @var Plan[]|null
     * @since 3.2.8
     */
    private ?array $_allPlans = null;

    /**
     * Returns all subscription plans
     *
     * @return Plan[]
     */
    public function getAllPlans(): array
    {
        return ArrayHelper::where($this->_getAllPlans(), 'isArchived', false);
    }

    /**
     * Returns all enabled subscription plans
     *
     * @return Plan[]
     * @noinspection PhpUnused
     */
    public function getAllEnabledPlans(): array
    {
        return ArrayHelper::whereMultiple($this->_getAllPlans(), ['enabled' => true, 'isArchived' => false]);
    }

    /**
     * Return all subscription plans for a gateway.
     *
     * @return Plan[]
     */
    public function getPlansByGatewayId(int $gatewayId): array
    {
        return ArrayHelper::whereMultiple($this->_getAllPlans(), ['gatewayId' => $gatewayId, 'isArchived' => false]);
    }

    /**
     * Return all subscription plans for a gateway.
     *
     * @return Plan[]
     * @deprecated in 4.0. Use [[getPlansByGatewayId]] instead.
     */
    public function getAllGatewayPlans(int $gatewayId): array
    {
        return $this->getPlansByGatewayId($gatewayId);
    }

    /**
     * Returns a subscription plan by its id.
     *
     * @param int $planId The plan id.
     */
    public function getPlanById(int $planId): ?Plan
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'id', $planId);
    }

    /**
     * Returns a subscription plan by its uid.
     *
     * @param string $planUid The plan uid.
     */
    public function getPlanByUid(string $planUid): ?Plan
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'uid', $planUid);
    }

    /**
     * Returns a subscription plan by its handle.
     *
     * @param string $handle the plan handle
     * @noinspection PhpUnused
     */
    public function getPlanByHandle(string $handle): ?Plan
    {
        return ArrayHelper::firstValue(ArrayHelper::whereMultiple($this->_getAllPlans(), ['handle' => $handle, 'isArchived' => false]));
    }

    /**
     * Returns a subscription plan by its reference.
     *
     * @param string $reference the plan reference
     */
    public function getPlanByReference(string $reference): ?Plan
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'reference', $reference);
    }

    /**
     * Returns plans which use the provided Entry for its "information"
     *
     * @param int $entryId The Entry ID to search by
     * @return Plan[]
     * @noinspection PhpUnused
     */
    public function getPlansByInformationEntryId(int $entryId): array
    {
        return ArrayHelper::where($this->_getAllPlans(), 'planInformationId', $entryId);
    }

    /**
     * Save a subscription plan
     *
     * @param Plan $plan The payment source being saved.
     * @param bool $runValidation should we validate this plan before saving.
     * @return bool Whether the plan was saved successfully
     * @throws InvalidConfigException if subscription plan not found by id.
     */
    public function savePlan(Plan $plan, bool $runValidation = true): bool
    {
        if ($plan->id) {
            $record = PlanRecord::findOne($plan->id);

            if (!$record) {
                throw new InvalidConfigException(Craft::t('commerce', 'No subscription plan exists with the ID “{id}”', ['id' => $plan->id]));
            }
        } else {
            $record = new PlanRecord();
        }

        // fire a 'beforeSavePlan' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_PLAN)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_PLAN, new PlanEvent([
                'plan' => $plan,
            ]));
        }

        if ($runValidation && !$plan->validate()) {
            Craft::info('Subscription plan not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->gatewayId = $plan->gatewayId;
        $record->name = $plan->name;
        $record->handle = $plan->handle;
        $record->planInformationId = $plan->planInformationId;
        $record->reference = $plan->reference;
        $record->planData = $plan->planData;
        $record->enabled = $plan->enabled;
        $record->isArchived = $plan->isArchived;
        $record->dateArchived = Db::prepareDateForDb($plan->dateArchived);
        $record->sortOrder = $plan->sortOrder ?? 99;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $plan->id = $record->id;

        // Fire an 'afterSavePlan' event.
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PLAN)) {
            $this->trigger(self::EVENT_AFTER_SAVE_PLAN, new PlanEvent([
                'plan' => $plan,
            ]));
        }

        // Reset cache/memoization
        $this->_allPlans = null;

        return true;
    }

    /**
     * Archive a subscription plan by its id.
     *
     * @param int $id The id
     * @throws InvalidConfigException
     */
    public function archivePlanById(int $id): bool
    {
        $plan = $this->getPlanById($id);

        if (!$plan) {
            return false;
        }

        // Fire an 'archivePlan' event.
        if ($this->hasEventHandlers(self::EVENT_ARCHIVE_PLAN)) {
            $this->trigger(self::EVENT_ARCHIVE_PLAN, new PlanEvent([
                'plan' => $plan,
            ]));
        }

        $plan->isArchived = true;
        $plan->dateArchived = DateTimeHelper::now();

        return $this->savePlan($plan);
    }

    /**
     * Reorders subscription plans by ids.
     *
     * @param array $ids Array of plans.
     * @return bool Always true.
     * @throws Exception
     */
    public function reorderPlans(array $ids): bool
    {
        $command = Craft::$app->getDb()->createCommand();

        foreach ($ids as $planOrder => $planId) {
            $command->update(Table::PLANS, ['sortOrder' => $planOrder + 1], ['id' => $planId])->execute();
        }

        // Reset cache/memoization
        $this->_allPlans = null;

        return true;
    }


    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createPlansQuery(): Query
    {
        return (new Query())
            ->select([
                'p.dateArchived',
                'p.dateCreated',
                'p.dateUpdated',
                'p.enabled',
                'p.gatewayId',
                'p.handle',
                'p.id',
                'p.isArchived',
                'p.name',
                'p.planData',
                'p.planInformationId',
                'p.reference',
                'p.sortOrder',
                'p.uid',
            ])
            ->leftJoin(['g' => Table::GATEWAYS], '[[g.id]] = [[p.gatewayId]]')
            ->where(['g.isArchived' => false])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->from(['p' => Table::PLANS]);
    }

    /**
     * Populate an array of plans from their database table rows
     *
     * @return Plan[]
     */
    private function _populatePlans(array $results): array
    {
        $plans = [];

        foreach ($results as $result) {
            try {
                $plans[] = $this->_populatePlan($result);
            } catch (InvalidConfigException) {
                continue; // Just skip this
            }
        }

        return $plans;
    }

    /**
     * Populate a payment plan model from database table row.
     *
     * @throws InvalidConfigException if the gateway does not support subscriptions
     */
    private function _populatePlan(array $result): Plan
    {
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($result['gatewayId']);

        if (!$gateway instanceof SubscriptionGateway) {
            throw new InvalidConfigException('This gateway does not support subscriptions');
        }

        $plan = $gateway->getPlanModel();

        $plan->setAttributes($result, false);

        return $plan;
    }

    /**
     * Get all plans memoized.
     *
     * @return array
     * @since 3.2.8
     */
    private function _getAllPlans(): array
    {
        if ($this->_allPlans === null) {
            $plans = $this->_createPlansQuery()->all();

            if (!empty($plans)) {
                $this->_allPlans = [];
                $plans = $this->_populatePlans($plans);
                foreach ($plans as $plan) {
                    $this->_allPlans[$plan->id] = $plan;
                }
            }
        }

        return $this->_allPlans ?? [];
    }
}
