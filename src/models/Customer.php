<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\User;
use yii\base\InvalidConfigException;

/**
 * Customer model
 *
 * @property Address[]    $addresses               the address for the customer
 * @property string       $email                   the customer's email address if it is related to a user
 * @property null|Address $lastUsedBillingAddress  the last used Billing Address used by the customer if it exists
 * @property null|Address $lastUsedShippingAddress the last used Shipping Address used by the customer if it exists
 * @property Order[]      $orders                  the order elements associated with this customer
 * @property User         $user                    the user element associated with this customer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
     * @var int The last used billing address
     */
    public $lastUsedBillingAddressId;

    /**
     * @var int The last used shipping address
     */
    public $lastUsedShippingAddressId;

    /**
     * @var User $_user
     */
    private $_user;

    // Public Methods
    // =========================================================================

    /**
     * Returns the email address of the customer as the string output.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getEmail();
    }

    /**
     * Returns the user element associated with this customer.
     *
     * @return User|null
     * @throws InvalidConfigException if [[userId]] is invalid
     */
    public function getUser()
    {
        if ($this->_user !== null) {
            return $this->_user;
        }

        if (!$this->userId) {
            return null;
        }

        if (($user = Craft::$app->getUsers()->getUserById($this->userId)) === null) {
            throw new InvalidConfigException("Invalid user ID: {$this->userId}");
        }

        return $this->_user = $user;
    }

    /**
     * Sets the user this customer is related to.
     *
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->_user = $user;
        $this->userId = $user->id;
    }

    /**
     * Returns the customers email address if it is related to a user.
     *
     * @return string
     */
    public function getEmail(): string
    {
        $user = $this->getUser();

        if ($user) {
            return $user->email;
        }

        return '';
    }

    /**
     * Returns the addresses associated with this customer.
     *
     * @return Address[]
     */
    public function getAddresses(): array
    {
        return Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($this->id);
    }

    /**
     * Returns an address for the customer.
     *
     * @param int|null $id the ID of the address to return, if known
     *
     * @return Address|null
     */
    public function getAddress($id = null)
    {
        $addresses = Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($this->id);
        foreach ($addresses as $address) {
            if ($id === $address->id) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Returns the order elements associated with this customer.
     *
     * @return Order[]
     */
    public function getOrders(): array
    {
        return Plugin::getInstance()->getOrders()->getOrdersByCustomer($this);
    }

    /**
     * Returns the last used Billing Address used by the customer if it exists.
     *
     * @return Address|null
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
     * @return Address|null
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
