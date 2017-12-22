<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\SubscriptionInterface;
use craft\commerce\models\Plan;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Plan as PlanRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

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
     * Returns allsubscription plans
     *
     * @return Plan[]
     */
    public function getAllPlans(): array
    {
        $results = $this->_createPlansQuery()
            ->all();

        $sources = [];

        foreach ($results as $result) {
            $sources[] = new Plan($result);
        }

        return $sources;
    }

    /**
     * Returns a subscription plan by its id.
     *
     * @param int $planId The plan id.
     *
     * @return Plan|null
     */
    public function getPlanById(int $planId)
    {
        $result = $this->_createPlansQuery()
            ->where(['id' => $planId])
            ->one();

        return $result ? new Plan($result) : null;
    }

    /**
     * Returns a subscription plan by its handle.
     *
     * @param string $handle the plan handle
     *
     * @return Plan|null
     */
    public function getPlanByHandle(string $handle)
    {
        $result = $this->_createPlansQuery()
            ->where(['handle' => $handle])
            ->one();

        return $result ? new Plan($result) : null;
    }

    /**
     * Save a subscription plan
     *
     * @param Plan $plan The payment source being saved.
     *
     * @return bool Whether the plan was saved successfully
     * @throws Exception if payment source not found by id.
     */
    public function savePlan(Plan $plan)
    {
        if ($plan->id) {
            $record = PlanRecord::findOne($plan->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No subscription plan exists with the ID “{id}”',
                    ['id' => $plan->id]));
            }
        } else {
            $record = new PlanRecord();
        }

        $record->gatewayId = $plan->gatewayId;
        $record->name = $plan->name;
        $record->handle = $plan->handle;
        $record->reference = $plan->reference;
        $record->billingPeriod = $plan->billingPeriod;
        $record->billingPeriodCount = $plan->billingPeriodCount;
        $record->paymentAmount = $plan->paymentAmount;
        $record->setupCost = $plan->setupCost;
        $record->currency = $plan->currency;
        $record->response = $plan->response;

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
     * Delete a payment source by it's id.
     *
     * @param int $id The id
     *
     * @return bool
     * @throws \Throwable in case something went wrong when deleting.
     */
    public function deletePlanById($id): bool
    {
        $record = PlanRecord::findOne($id);

        if ($record) {
            $gateway = Commerce::getInstance()->getGateways()->getGatewayById($record->gatewayId);

            if ($gateway instanceof SubscriptionInterface && $gateway->supportsPlanOperations()) {
                $gateway->deletePlan($record->reference);
            }

            return (bool)$record->delete();
        }

        return false;
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
                'billingPeriod',
                'billingPeriodCount',
                'paymentAmount',
                'setupCost',
                'currency',
                'response',
            ])
            ->from(['{{%commerce_plans}}']);
    }

}
