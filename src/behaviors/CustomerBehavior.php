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
use craft\commerce\records\UserAddress;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * Commerce User behavior.
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
     * @return Address[]
     * @throws InvalidConfigException
     */
    public function getAddresses(): array
    {
        if (!$this->owner->id) {
            return [];
        }

        if (null === $this->_addresses) {
            $this->_addresses = Plugin::getInstance()->getAddresses()->getAddressesByUserId($this->owner->id);
        }

        return $this->_addresses ?? [];
    }

    /**
     * @param int $id
     * @return Address|null
     * @throws InvalidConfigException
     */
    public function getAddressById(int $id): ?Address
    {
        return ArrayHelper::firstWhere($this->getAddresses(), 'id', $id);
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
            $this->_subscriptions = Subscription::find()->user($this->owner)->status(null)->all();
        }

        return $this->_subscriptions ?? [];
    }

    /**
     * Returns the  customer's primary billing address if it exists.
     *
     * @return Address|null
     * @throws InvalidConfigException
     */
    public function getPrimaryBillingAddress(): ?Address
    {
        if ($primaryBillingAddressId = $this->getPrimaryBillingAddressId()) {
            return ArrayHelper::firstWhere($this->getAddresses(), 'id', $primaryBillingAddressId);
        }

        return null;
    }

    /**
     * Returns the customer's primary shipping address if it exists.
     *
     * @return Address|null
     * @throws InvalidConfigException
     */
    public function getPrimaryShippingAddress(): ?Address
    {
        if ($primaryShippingAddressId = $this->getPrimaryShippingAddressId()) {
            return ArrayHelper::firstWhere($this->getAddresses(), 'id', $primaryShippingAddressId);
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getPrimaryBillingAddressId(): ?int
    {
        if (null === $this->_primaryBillingAddressId && $this->owner->id) {
            $this->setPrimaryBillingAddressId(UserAddress::find()
                ->select(['addressId'])
                ->where([
                    'userId' => $this->owner->id,
                    'isPrimaryBillingAddress' => true,
                ])
                ->scalar()
            );
        }

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
     * @return int|null
     */
    public function getPrimaryShippingAddressId(): ?int
    {
        if (null === $this->_primaryShippingAddressId && $this->owner->id) {
            $this->setPrimaryShippingAddressId(UserAddress::find()
                ->select(['addressId'])
                ->where([
                    'userId' => $this->owner->id,
                    'isPrimaryShippingAddress' => true
                ])
                ->scalar()
            );
        }

        return $this->_primaryShippingAddressId;
    }

    /**
     * @param int|null $primaryShippingAddressId
     */
    public function setPrimaryShippingAddressId(?int $primaryShippingAddressId): void
    {
        $this->_primaryShippingAddressId = $primaryShippingAddressId;
    }
}