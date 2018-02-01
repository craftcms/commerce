<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\Plan;
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
 * @since  2.0
 */
class Plans extends Component
{
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
     * Returns a subscription plan by its id.
     *
     * @param int $planId The plan id.
     *
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
     * Returns a subscription plan by its handle.
     *
     * @param string $handle the plan handle
     *
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
     * Save a subscription plan
     *
     * @param Plan $plan The payment source being saved.
     *
     * @return bool Whether the plan was saved successfully
     * @throws InvalidConfigException if subscription plan not found by id.
     */
    public function savePlan(Plan $plan)
    {
        if ($plan->id) {
            $record = PlanRecord::findOne($plan->id);

            if (!$record) {
                throw new InvalidConfigException(Craft::t('commerce', 'No subscription plan exists with the ID “{id}”', ['id' => $plan->id]));
            }
        } else {
            $record = new PlanRecord();
        }

        $record->gatewayId = $plan->gatewayId;
        $record->name = $plan->name;
        $record->handle = $plan->handle;
        $record->reference = $plan->reference;
        $record->planData = $plan->planData;
        $record->enabled = $plan->enabled;
        $record->isArchived = $plan->isArchived;
        $record->dateArchived = $plan->dateArchived;

        $record->validate();
        $plan->addErrors($record->getErrors());

        if (!$plan->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $plan->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * Archive a subscription plan by it's id.
     *
     * @param int $id The id
     *
     * @return bool
     * @throws InvalidConfigException
     */
    public function archivePlanById(int $id): bool
    {
        $plan = $this->getPlanById($id);

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
                'reference',
                'planData',
                'enabled',
                'isArchived',
                'dateArchived'
            ])
            ->where(['isArchived' => false])
            ->from(['{{%commerce_plans}}']);
    }

    /**
     * Populate a payment plan model from database table row.
     *
     * @param array $result
     *
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
