<?php

namespace craft\commerce\base;

use craft\base\ElementInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin as Commerce;
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
     * Return the subscription count for this plan.
     *
     * @return int
     */
    public function getSubscriptionCount()
    {
        return Commerce::getInstance()->getSubscriptions()->getSubscriptionCountForPlanById($this->id);
    }

    /**
     * Return the subscription count for this plan.
     *
     * @param int $userId the user id
     *
     * @return ElementInterface|false
     */
    public function getActiveUserSubscription(int $userId)
    {
        return Subscription::find()
            ->userId($userId)
            ->planId($this->id)
            ->status(Subscription::STATUS_ACTIVE)
            ->one();
    }
}
