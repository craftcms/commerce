<?php
namespace Craft;

/**
 * Customer model.
 *
 * @property int $id
 * @property int $userId
 * @property string $email
 * @property int $lastUsedBillingAddressId
 * @property int $lastUsedShippingAddressId
 *
 * @property Commerce_AddressModel[] $addresses
 * @property Commerce_OrderModel[] $orders
 * @property UserModel $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_CustomerModel extends BaseModel
{
    private $_user;

    /*
     * Returns the email address of the customer as the string output.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getAttribute('email');
    }

    /**
     * Returns the user element associated with this customer.
     *
     * @return UserModel|null
     */
    public function getUser()
    {
        if (!isset($this->_user)) {
            $this->_user = craft()->users->getUserById($this->userId);
        }

        return $this->_user;
    }

    /**
     * Returns the addresses associated with this customer.
     *
     * @return Commerce_AddressModel[]
     */
    public function getAddresses()
    {
        return craft()->commerce_addresses->getAddressesByCustomerId($this->id);
    }

    /**
     * Returns the order elements associated with this customer.
     *
     * @return Commerce_OrderModel[]
     */
    public function getOrders()
    {
        return craft()->commerce_orders->getOrdersByCustomer($this);
    }

    /**
     * Returns the last used Billing Address used by the customer if it exists.
     *
     * @return Commerce_AddressModel|null
     */
    public function getLastUsedBillingAddress()
    {
        if ($this->lastUsedBillingAddressId) {
            $address = $this->getAddress($this->lastUsedBillingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Gets a single address of a customer by id
     *
     * @param null $id
     * @return mixed
     */
    public function getAddress($id = null)
    {
        $addresses = craft()->commerce_addresses->getAddressesByCustomerId($this->id);
        foreach ($addresses as $address) {
            if ($id == $address->id) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Returns the last used Shipping Address used by the customer if it exists.
     *
     * @return Commerce_AddressModel|null
     */
    public function getLastUsedShippingAddress()
    {
        if ($this->lastUsedShippingAddressId) {
            $address = $this->getAddress($this->lastUsedShippingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id' => AttributeType::Number,
            'userId' => AttributeType::Number,
            'email' => AttributeType::String,
            'lastUsedBillingAddressId' => AttributeType::Number,
            'lastUsedShippingAddressId' => AttributeType::Number
        ]);
    }
}
