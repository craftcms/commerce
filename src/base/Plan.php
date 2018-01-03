<?php

namespace craft\commerce\base;

use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use craft\helpers\Json;

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
     * @var GatewayInterface|null $_gateway
     */
    private $_gateway;

    /**
     * @var mixed
     */
    private $_data;

    // Public Methods
    // =========================================================================

    /**
     * Returns the billing plan handle
     *
     * @return string
     */
    public function __toString()
    {
        return $this->handle;
    }

    /**
     * Returns the user element associated with this customer.
     *
     * @return GatewayInterface|null
     */
    public function getGateway()
    {
        if (null === $this->_gateway) {
            $this->_gateway = Commerce::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @return mixed
     */
    public function getPlanData()
    {
        if ($this->_data === null) {
            $this->_data = Json::decodeIfJson($this->response);
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
}
