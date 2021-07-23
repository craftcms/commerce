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
use craft\helpers\Db;
use DateTime;
use yii\base\Component;
use yii\base\InvalidConfigException;

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
    const EVENT_ARCHIVE_PLAN = 'archivePlan';

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
    const EVENT_BEFORE_SAVE_PLAN = 'beforeSavePlan';

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
    const EVENT_AFTER_SAVE_PLAN = 'afterSavePlan';

    /**
     * Memoized array of plans.
     *
     * @var Plan[]|null
     * @since 3.2.8
     */
    private $_allPlans;

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
     */
    public function getAllEnabledPlans(): array
    {
        return ArrayHelper::whereMultiple($this->_getAllPlans(), ['enabled' => true, 'isArchived' => false]);
    }

    /**
     * Return all subscription plans for a gateway.
     *
     * @param int $gatewayId
     * @return Plan[]
     */
    public function getAllGatewayPlans(int $gatewayId): array
    {
        return ArrayHelper::whereMultiple($this->_getAllPlans(), ['gatewayId' => $gatewayId, 'isArchived' => false]);
    }

    /**
     * Returns a subscription plan by its id.
     *
     * @param int $planId The plan id.
     * @return Plan|null
     */
    public function getPlanById(int $planId)
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'id', $planId);
    }

    /**
     * Returns a subscription plan by its uid.
     *
     * @param string $planUid The plan uid.
     * @return Plan|null
     */
    public function getPlanByUid(string $planUid)
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'uid', $planUid);
    }

    /**
     * Returns a subscription plan by its handle.
     *
     * @param string $handle the plan handle
     * @return Plan|null
     */
    public function getPlanByHandle(string $handle)
    {
        return ArrayHelper::firstValue(ArrayHelper::whereMultiple($this->_getAllPlans(), ['handle' => $handle, 'isArchived' => false]));
    }

    /**
     * Returns a subscription plan by its reference.
     *
     * @param string $reference the plan reference
     * @return Plan|null
     */
    public function getPlanByReference(string $reference)
    {
        return ArrayHelper::firstWhere($this->_getAllPlans(), 'reference', $reference);
    }

    /**
     * Returns plans which use the provided Entry for its "information"
     *
     * @param int $entryId The Entry ID to search by
     * @return Plan[]
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
        $record->dateArchived = $plan->dateArchived;
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
     * @return bool
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
        $plan->dateArchived = Db::prepareDateForDb(new DateTime());

        return $this->savePlan($plan);
    }

    /**
     * Reorders subscription plans by ids.
     *
     * @param array $ids Array of plans.
     * @return bool Always true.
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
                'dateArchived',
                'dateCreated',
                'dateUpdated',
                'enabled',
                'gatewayId',
                'handle',
                'id',
                'isArchived',
                'name',
                'planData',
                'planInformationId',
                'reference',
                'sortOrder',
                'uid',
            ])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->from([Table::PLANS]);
    }

    /**
     * Populate an array of plans from their database table rows
     *
     * @param array $results
     * @return Plan[]
     */
    private function _populatePlans(array $results): array
    {
        $plans = [];

        foreach ($results as $result) {
            try {
                $plans[] = $this->_populatePlan($result);
            } catch (InvalidConfigException $exception) {
                continue; // Just skip this
            }
        }

        return $plans;
    }

    /**
     * Populate a payment plan model from database table row.
     *
     * @param array $result
     * @return Plan
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
    private function _getAllPlans()
    {
        if ($this->_allPlans === null) {
            $plans = $this->_createPlansQuery()->all();

            $this->_allPlans = [];
            if (!empty($plans)) {
                $plans = $this->_populatePlans($plans);
                foreach ($plans as $plan) {
                    $this->_allPlans[$plan->id] = $plan;
                }
            }
        }

        return $this->_allPlans;
    }
}
