<?php

namespace craft\commerce\base;

use craft\base\ElementInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin as Commerce;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Json;
use yii\base\InvalidConfigException;

/**
 * Plan model
 *
 * @property GatewayInterface $gateway
 * @property User             $user
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
abstract class Plan extends Model implements PlanInterface
{
    // Traits
    // =========================================================================

    use PlanTrait;

    // Properties
    // =========================================================================

    /**
     * @var SubscriptionGatewayInterface|null $_gateway
     */
    private $_gateway;

    /**
     * @var mixed
     */
    private $_data;

    // Public Methods
    // =========================================================================

    /**
     * Returns the billing plan friendly name
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getFriendlyPlanName();
    }

    /**
     * Returns the user element associated with this customer.
     *
     * @return SubscriptionGatewayInterface|null
     * @throws InvalidConfigException if gateway does not support subscriptions
     */
    public function getGateway()
    {
        if (null === $this->_gateway) {
            $this->_gateway = Commerce::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        if ($this->_gateway && !$this->_gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('This gateway does not support subscriptions');
        }

        return $this->_gateway;
    }

    /**
     * Get the stored plan data.
     *
     * @return mixed
     */
    public function getPlanData()
    {
        if ($this->_data === null) {
            $this->_data = Json::decodeIfJson($this->planData);
        }

        return $this->_data;
    }

    /**
     * Get the plan's related Entry element, if any.
     *
     * @return Entry|null
     */
    public function getInformation()
    {
        if ($this->planInformationId) {
            return Entry::find()->id($this->planInformationId)->one();
        }

        return null;
    }

    /**
     * Return the subscription count for this plan.
     *
     * @return int
     */
    public function getSubscriptionCount()
    {
        return Commerce::getInstance()->getSubscriptions()->getSubscriptionCountForPlanById($this->id);
    }

    /**
     * Whether there exists an active subscription for this plan for this user.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function hasActiveSubscription(int $userId): bool
    {
        return (bool) count($this->getActiveUserSubscription($userId));
    }

    /**
     * Return the subscription count for this plan.
     *
     * @param int $userId the user id
     *
     * @return ElementInterface[]
     */
    public function getActiveUserSubscriptions(int $userId)
    {
        return Subscription::find()
            ->userId($userId)
            ->planId($this->id)
            ->status(Subscription::STATUS_ACTIVE)
            ->all();
    }
}
