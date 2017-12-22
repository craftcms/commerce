<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\Model;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;

/**
 * Plan model
 *
 * @property GatewayInterface $gateway
 * @property User             $user
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Plan extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int Payment source ID
     */
    public $id;

    /**
     * @var int The gateway ID.
     */
    public $gatewayId;

    /**
     * @var string plan name
     */
    public $name;

    /**
     * @var string plan handle
     */
    public $handle;

    /**
     * @var string plan reference on the gateway
     */
    public $reference;

    /**
     * @var string plan billing period - day, week, month or year
     */
    public $billingPeriod;

    /**
     * @var int after how many billing periods should the subscriber be billed
     */
    public $billingPeriodCount;

    /**
     * @var float payment amount
     */
    public $paymentAmount;

    /**
     * @var float initial cost
     */
    public $setupCost;

    /**
     * @var string currency iso code
     */
    public $currency;

    /**
     * @var string gateway response
     */
    public $response;

    /**
     * @var GatewayInterface|null $_gateway
     */
    private $_gateway;

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

}
