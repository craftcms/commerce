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
use craft\commerce\events\PlanEvent;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Plan as PlanRecord;
use craft\db\Query;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Plans service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property array|\craft\commerce\base\Plan[] $allEnabledPlans
 * @property array|\craft\commerce\base\Plan[] $allPlans
 */
class Plans extends Component
{
    // Constants
    // =========================================================================

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

    // Public Methods
    // =========================================================================

    /**
     * Returns all subscription plans
     *
     * @return Plan[]
     */
    public function getAllPlans(): array
    {
        $results = $this->_createPlansQuery()
            ->all();

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
     * Returns all enabled subscription plans
     *
     * @return Plan[]
     */
    public function getAllEnabledPlans(): array
    {
        $results = $this->_createPlansQuery()
            ->where(['enabled' => true])
            ->all();

        $sources = [];

        foreach ($results as $result) {
            try {
                $sources[] = $this->_populatePlan($result);
            } catch (InvalidConfigException $exception) {
                continue; // Just skip this
            }
        }

        return $sources;
    }

    /**
     * Return all subscription plans for a gateway.
     *
     * @param int $gatewayId
     * @return Plan[]
     */
    public function getAllGatewayPlans(int $gatewayId): array
    {
        $results = $this->_createPlansQuery()
            ->where(['gatewayId' => $gatewayId])
            ->all();

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
     * Returns a subscription plan by its id.
     *
     * @param int $planId The plan id.
     * @return Plan|null
     * @throws InvalidConfigException if the plan configuration is not correct
     */
    public function getPlanById(int $planId)
    {
        $result = $this->_createPlansQuery()
            ->where(['id' => $planId])
            ->one();

        return $result ? $this->_populatePlan($result) : null;
    }

    /**
     * Returns a subscription plan by its uid.
     *
     * @param string $planUid The plan uid.
     * @return Plan|null
     * @throws InvalidConfigException if the plan configuration is not correct
     */
    public function getPlanByUid(string $planUid)
    {
        $result = $this->_createPlansQuery()
            ->where(['uid' => $planUid])
            ->one();

        return $result ? $this->_populatePlan($result) : null;
    }

    /**
     * Returns a subscription plan by its handle.
     *
     * @param string $handle the plan handle
     * @return Plan|null
     * @throws InvalidConfigException if the plan configuration is not correct
     */
    public function getPlanByHandle(string $handle)
    {
        $result = $this->_createPlansQuery()
            ->where(['handle' => $handle])
            ->one();

        return $result ? $this->_populatePlan($result) : null;
    }

    /**
     * Returns a subscription plan by its reference.
     *
     * @param string $reference the plan reference
     * @return Plan|null
     * @throws InvalidConfigException if the plan configuration is not correct
     */
    public function getPlanByReference(string $reference)
    {
        $result = $this->_createPlansQuery()
            ->where(['reference' => $reference])
            ->one();

        return $result ? $this->_populatePlan($result) : null;
    }

    /**
     * Save a subscription plan
     *
     * @param Plan $plan The payment source being saved.
     * @param bool $runValidation should we validate this plan before saving.
     * @return bool Whether the plan was saved successfully
     * @throws InvalidConfigException if subscription plan not found by id.
     */
    public function savePlan(Plan $plan, bool $runValidation = true)
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
        $plan->dateArchived = Db::prepareDateForDb(new \DateTime());

        return $this->savePlan($plan);
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createPlansQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'gatewayId',
                'name',
                'handle',
                'planInformationId',
                'reference',
                'planData',
                'enabled',
                'isArchived',
                'dateArchived',
                'uid'
            ])
            ->where(['isArchived' => false])
            ->from(['{{%commerce_plans}}']);
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
        $gateway = Commerce::getInstance()->getGateways()->getGatewayById($result['gatewayId']);

        if (!$gateway instanceof SubscriptionGateway) {
            throw new InvalidConfigException('This gateway does not support subscriptions');
        }

        $plan = $gateway->getPlanModel();

        $plan->setAttributes($result, false);

        return $plan;
    }
}
