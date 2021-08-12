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
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use DateTime;
use Exception;

/**
 * Customer model
 *
 * @property Address[] $addresses the address for the customer
 * @property string $email the customer's email address if it is related to a user
 * @property null|Address $primaryBillingAddress the primary Billing Address used by the customer if it exists
 * @property null|Address $primaryShippingAddress the primary Shipping Address used by the customer if it exists
 * @property Order[] $orders the order elements associated with this customer
 * @property-read Subscription[] $subscriptions
 * @property User $user the user element associated with this customer
 * @property-read array $activeCarts The active carts this customer has
 * @property-read array $inactiveCarts The Inactive carts this customer has
 * @property-read string $cpEditUrl Link URL to this customer in the CP
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customer extends Model
{
    /**
     * @var int|null Customer ID
     */
    public ?int $id = null;

    /**
     * @var int|null The user ID
     */
    public ?int $userId = null;

    /**
     * @var int|null The primary billing address id
     */
    public ?int $primaryBillingAddressId = null;

    /**
     * @var int|null The primary shipping address id
     */
    public ?int $primaryShippingAddressId = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated;

    /**
     * @var User|null $_user
     */
    private ?User $_user = null;

    /**
     * @return null|string
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Customer');
    }

    /**
     * @return string
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'customer');
    }

    /**
     * @return string
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Customers');
    }

    /**
     * @return string
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'customers');
    }

    /**
     * Returns the email address of the customer as the string output.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getEmail();
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
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
     */
    public function getUser(): ?User
    {
        if ($this->_user !== null) {
            return $this->_user;
        }

        if (!$this->userId) {
            return null;
        }

        $this->_user = Craft::$app->getUsers()->getUserById($this->userId);

        return $this->_user;
    }

    /**
     * Sets the user this customer is related to.
     *
     * @param User $user
     */
    public function setUser(User $user): void
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

        return $user->email ?? '';
    }

    /**
     * Returns the addresses associated with this customer.
     *
     * @return Address[]
     */
    public function getAddresses(): array
    {
        if ($this->id) {
            return Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($this->id);
        }

        return [];
    }

    /**
     * Returns an address for the customer.
     *
     * @param int|null $id the ID of the address to return, if known
     * @return Address|null
     */
    public function getAddressById(int $id = null): ?Address
    {
        return ArrayHelper::firstWhere($this->getAddresses(), 'id', $id);
    }

    /**
     * @return string
     * @since 3.0
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/customers/' . $this->id);
    }

    /**
     * Returns the completed order elements associated with this customer.
     * Orders are returned with the most recent first.
     *
     * @return Order[]
     */
    public function getOrders(): array
    {
        return Order::find()
            ->customer($this)
            ->isCompleted()
            ->withAll()
            ->orderBy('dateOrdered DESC')
            ->all();
    }

    /**
     * @return array
     * @throws Exception
     * @since 2.2
     */
    public function getActiveCarts(): array
    {
        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();
        return Order::find()->customer($this)->isCompleted(false)->dateUpdated('>= ' . $edge)->orderBy('dateUpdated DESC')->all();
    }

    /**
     * @return array
     * @throws Exception
     * @since 2.2
     */
    public function getInactiveCarts(): array
    {
        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();
        return Order::find()->customer($this)->isCompleted(false)->dateUpdated('< ' . $edge)->orderBy('dateUpdated ASC')->all();
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
    public function getPrimaryBillingAddress(): ?Address
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
    public function getPrimaryShippingAddress(): ?Address
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
