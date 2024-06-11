<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\behaviors;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin;
use craft\commerce\records\Customer;
use craft\elements\Address;
use craft\elements\User;
use craft\events\DefineRulesEvent;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use RuntimeException;
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
 * @property-read Order[] $orders
 * @property-read Address[] $addresses
 * @property null|int $primaryPaymentSourceId
 * @property-read null|PaymentSource $primaryPaymentSource
 * @property User $owner
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class CustomerBehavior extends Behavior
{
    /**
     * @var int|null
     */
    private ?int $_primaryBillingAddressId = null;

    /**
     * @var int|null
     */
    private ?int $_primaryShippingAddressId = null;

    /**
     * @var int|null
     * @since 4.2
     */
    private ?int $_primaryPaymentSourceId = null;

    /**
     * @var array|null
     */
    private ?array $_subscriptions = null;

    /**
     * @var Customer|null
     * @since 4.2
     */
    private ?Customer $_customer = null;

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (!$owner instanceof User) {
            throw new RuntimeException('CustomerBehavior can only be attached to a User element');
        }

        parent::attach($owner);
    }

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            User::EVENT_AFTER_SAVE => 'afterSaveUserHandler',
            User::EVENT_DEFINE_RULES => 'defineRules',
        ];
    }

    /**
     * @param DefineRulesEvent $event
     * @throws InvalidConfigException
     */
    public function defineRules(DefineRulesEvent $event): void
    {
        $event->rules[] = [['primaryBillingAddressId', 'primaryShippingAddressId'], 'safe'];
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws InvalidConfigException
     */
    public function afterSaveUserHandler(ModelEvent $event)
    {
        /** @var User|CustomerBehavior $user */
        $user = $event->sender;

        if ($user->primaryBillingAddressId) {
            Plugin::getInstance()->getCustomers()->savePrimaryBillingAddressId($user, $user->primaryBillingAddressId);
        }

        if ($user->primaryShippingAddressId) {
            Plugin::getInstance()->getCustomers()->savePrimaryShippingAddressId($user, $user->primaryShippingAddressId);
        }

        if ($user->primaryPaymentSourceId) {
            Plugin::getInstance()->getCustomers()->savePrimaryPaymentSourceId($user, $user->primaryPaymentSourceId);
        }

        // Keep orders `email` column up-to-date with user's email address
        if ($user->email && $user->id) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::ORDERS, ['email' => $user->email], ['customerId' => $user->id], [], false)
                ->execute();
        }
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
        if (!isset($this->_primaryBillingAddressId)) {
            $customer = $this->_getCustomerRecord();
            if ($customer) {
                $this->_primaryBillingAddressId = $customer->primaryBillingAddressId;
            }
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
     * @return Address|null
     */
    public function getPrimaryBillingAddress(): ?Address
    {
        return ArrayHelper::firstWhere($this->owner->getAddresses(), 'id', $this->getPrimaryBillingAddressId());
    }

    /**
     * @return int|null
     */
    public function getPrimaryShippingAddressId(): ?int
    {
        if (!isset($this->_primaryShippingAddressId)) {
            $customer = $this->_getCustomerRecord();
            if ($customer) {
                $this->_primaryShippingAddressId = $customer->primaryShippingAddressId;
            }
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

    /**
     * @return Address|null
     */
    public function getPrimaryShippingAddress(): ?Address
    {
        return ArrayHelper::firstWhere($this->owner->getAddresses(), 'id', $this->getPrimaryShippingAddressId());
    }

    /**
     * @param int|null $paymentSourceId
     * @return void
     * @since 4.2
     */
    public function setPrimaryPaymentSourceId(?int $paymentSourceId): void
    {
        $this->_primaryPaymentSourceId = $paymentSourceId;
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     * @since 4.2
     */
    public function getPrimaryPaymentSourceId(): ?int
    {
        // @TODO remove fallback code and just always enforce a primary payment source if one exists at next breaking change
        if ($this->_primaryPaymentSourceId === null) {
            $customer = $this->_getCustomerRecord();
            if (!$customer) {
                return $this->_primaryPaymentSourceId;
            }

            if ($customer->primaryPaymentSourceId) {
                $this->_primaryPaymentSourceId = $customer->primaryPaymentSourceId;
                return $this->_primaryPaymentSourceId;
            }

            $paymentSource = $this->getPrimaryPaymentSource();
            if ($paymentSource) {
                $this->_primaryPaymentSourceId = $paymentSource->id;
            }
        }

        return $this->_primaryPaymentSourceId;
    }

    /**
     * @return PaymentSource|null
     * @throws InvalidConfigException
     * @since 4.2
     */
    public function getPrimaryPaymentSource(): ?PaymentSource
    {
        $paymentSources = Plugin::getInstance()->getPaymentSources()->getAllPaymentSourcesByCustomerId(customerId: $this->owner->id);
        if ($paymentSources->isEmpty()) {
            return null;
        }

        if (!$this->_primaryPaymentSourceId) {
            return $paymentSources->first();
        }

        return $paymentSources->firstWhere('id', $this->_primaryPaymentSourceId);
    }

    /**
     * @return Customer|null
     * @since 4.2
     */
    private function _getCustomerRecord(): ?Customer
    {
        if (!$this->_customer instanceof Customer) {
            /** @var Customer|null $customer */
            $customer = Customer::find()->where(['customerId' => $this->owner->id])->one();
            $this->_customer = $customer;
        }

        return $this->_customer;
    }
}
