<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craft\elements\User;
use yii\base\InvalidConfigException;

/**
 * Customer model
 *
 * @property Address[] $addresses the address for the customer
 * @property string $email the customer's email address if it is related to a user
 * @property null|Address $primaryBillingAddress the primary Billing Address used by the customer if it exists
 * @property null|Address $primaryShippingAddress the primary Shipping Address used by the customer if it exists
 * @property Order[] $orders the order elements associated with this customer
 * @property User $user the user element associated with this customer
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customer extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int|null Customer ID
     */
    public $id;

    /**
     * @var int The user ID
     */
    public $userId;

    /**
     * @var int The primary billing address id
     */
    public $primaryBillingAddressId;

    /**
     * @var int The primary shipping address id
     */
    public $primaryShippingAddressId;

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
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'user',
            'email',
            'addresses',
            'orders',
            'subscriptions',
            'primaryBillingAddress',
            'primaryShippingAddress',
        ];
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
            return null; // They are probably soft-deleted
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
     * @return Address|null
     */
    public function getAddressById(int $id = null)
    {
        $addresses = $this->getAddresses();
        foreach ($addresses as $address) {
            if ($id == $address->id) {
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
        return Order::find()->customer($this)->isCompleted()->all();
    }

    /**
     * Returns the subscription elements associated with this customer.
     *
     * @return Subscription[]
     */
    public function getSubscriptions(): array
    {
        $user = $this->getUser();

        if (!$user) {
            return [];
        }

        return Subscription::find()->user($user)->anyStatus()->all();
    }

    /**
     * Returns the  customer's primary billing address if it exists.
     *
     * @return Address|null
     */
    public function getPrimaryBillingAddress()
    {
        if ($this->primaryBillingAddressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($this->primaryBillingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Returns the customer's primary shipping address if it exists.
     *
     * @return Address|null
     */
    public function getPrimaryShippingAddress()
    {
        if ($this->primaryShippingAddressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($this->primaryShippingAddressId);
            if ($address) {
                return $address;
            }
        }

        return null;
    }
}
