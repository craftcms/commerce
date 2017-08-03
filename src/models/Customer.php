<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;

/**
 * Customer model
 *
 * @property \craft\commerce\models\Address[] $addresses
 * @property \craft\commerce\models\Orders[]  $orders
 * @property \craft\elements\User             $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Customer extends Model
{

    // Properties
    // =========================================================================

    /**
     * @var int Customer ID
     */
    public $id;

    /**
     * @var int The user ID
     */
    public $userId;

    /**
     * @var string Email
     */
    public $email;

    /**
     * @var int The last used billing address
     */
    public $lastUsedBillingAddressId;

    /**
     * @var int The last used shipping address
     */
    public $lastUsedShippingAddressId;

    /**
     * @var \craft\commerce\models\User $_user
     */
    private $_user;

    /**
     * Returns the email address of the customer as the string output.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->email;
    }

    /**
     * Returns the user element associated with this customer.
     *
     * @return \craft\elements\User|null
     */
    public function getUser()
    {
        if (null === $this->_user && $this->userId)
        {
            $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->_user;
    }

    /**
     * Returns the addresses associated with this customer.
     *
     * @return \craft\commerce\models\address[]
     */
    public function getAddresses()
    {
        return Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($this->id);
    }

    /**
     * Returns the order elements associated with this customer.
     *
     * @return \craft\commerce\elements\Order[]
     */
    public function getOrders()
    {
        return Plugin::getInstance()->getOrders()->getOrdersByCustomer($this);
    }

    /**
     * Returns the last used Billing Address used by the customer if it exists.
     *
     * @return \craft\commerce\models\Address|null
     */
    public function getLastUsedBillingAddress()
    {
        if ($this->lastUsedBillingAddressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($this->lastUsedBillingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }


    /**
     * Returns the last used Shipping Address used by the customer if it exists.
     *
     * @return \craft\commerce\models\Address|null
     */
    public function getLastUsedShippingAddress()
    {
        if ($this->lastUsedShippingAddressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($this->lastUsedShippingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }
}
