<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\behaviors;

use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\elements\Address as AddressElement;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * Customer behavior.
 *
 * @property-read array $activeCarts
 * @property-read null|Address $primaryShippingAddress
 * @property-read array $inactiveCarts
 * @property null|int $primaryShippingAddressId
 * @property-read null|Address $primaryBillingAddress
 * @property-read Subscription[] $subscriptions
 * @property null|int $primaryBillingAddressId
 * @property-read Address[] $addresses
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 * @method AddressElement[] getAddresses()
 */
class CustomerBehavior extends Behavior
{
    /** @var User */
    public $owner;

    /**
     * @var Address[]|null
     */
    private ?array $_addresses = null;

    /**
     * @var int|null
     */
    private ?int $_primaryBillingAddressId = null;

    /**
     * @var int|null
     */
    private ?int $_primaryShippingAddressId = null;

    /**
     * @var array|null
     */
    private ?array $_subscriptions = null;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof User) {
            throw new \RuntimeException('CustomerBehavior can only be attached to a User element');
        }

        parent::attach($owner);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getActiveCarts(): array
    {
        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();
        return Order::find()
            ->customer($this->owner)
            ->isCompleted(false)
            ->dateUpdated('>= ' . $edge)
            ->orderBy('dateUpdated DESC')
            ->all();
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getInactiveCarts(): array
    {
        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();
        return Order::find()
            ->customer($this->owner)
            ->isCompleted(false)
            ->dateUpdated('< ' . $edge)
            ->orderBy('dateUpdated ASC')
            ->all();
    }

    /**
     * Returns the completed order elements associated with this user.
     * Orders are returned with the most recent first.
     *
     * @return Order[]
     */
    public function getOrders(): array
    {
        return Order::find()
            ->customer($this->owner)
            ->isCompleted()
            ->withAll()
            ->orderBy('dateOrdered DESC')
            ->all();
    }

    /**
     * Returns the subscription elements associated with this customer.
     *
     * @return Subscription[]
     */
    public function getSubscriptions(): array
    {
        if (null === $this->_subscriptions) {
            $this->_subscriptions = Subscription::find()
                ->user($this->owner)
                ->status(null)
                ->all();
        }

        return $this->_subscriptions ?? [];
    }

    /**
     * @return int|null
     */
    public function getPrimaryBillingAddressId(): ?int
    {
        return $this->_primaryBillingAddressId;
    }

    /**
     * @param int|null $primaryBillingAddressId
     */
    public function setPrimaryBillingAddressId(?int $primaryBillingAddressId): void
    {
        $this->_primaryBillingAddressId = $primaryBillingAddressId;
    }

    /**
     * @return AddressElement|null
     */
    public function getPrimaryBillingAddress(): ?AddressElement
    {
        return ArrayHelper::firstWhere($this->owner->getAddresses(), 'id', $this->getPrimaryBillingAddressId());
    }

    /**
     * @return int|null
     */
    public function getPrimaryShippingAddressId(): ?int
    {
        return $this->_primaryShippingAddressId;
    }

    /**
     * @param int|null $primaryShippingAddressId
     */
    public function setPrimaryShippingAddressId(?int $primaryShippingAddressId): void
    {
        $this->_primaryShippingAddressId = $primaryShippingAddressId;
    }

    /**
     * @return AddressElement|null
     */
    public function getPrimaryShippingAddress(): ?AddressElement
    {
        return ArrayHelper::firstWhere($this->owner->getAddresses(), 'id', $this->getPrimaryShippingAddressId());
    }
}