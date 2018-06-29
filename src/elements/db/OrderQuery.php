<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\base\Gateway;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;

/**
 * OrderQuery represents a SELECT SQL statement for orders in a way that is independent of DBMS.
 * @method Order[]|array all($db = null)
 * @method Order|array|null one($db = null)
 * @method Order|array|null nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var string The order number of the resulting entry.
     */
    public $number;

    /**
     * @var string The email address the resulting emails must have.
     */
    public $email;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public $isCompleted;

    /**
     * @var mixed The Date Ordered date that the resulting orders must have.
     */
    public $dateOrdered;

    /**
     * @var mixed The Updated On date that the resulting orders must have.
     */
    public $updatedOn;

    /**
     * @var mixed The Expiry Date that the resulting orders must have.
     */
    public $expiryDate;

    /**
     * @var mixed The date the order was paid.
     */
    public $datePaid;

    /**
     * @var OrderStatus|int The Order Status that the resulting orders must have.
     */
    public $orderStatus;

    /**
     * @var int The Order Status ID that the resulting orders must have.
     */
    public $orderStatusId;

    /**
     * @var Customer|int The customer  that the resulting orders must have.
     */
    public $customer;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public $customerId;

    /**
     * @var Gateway|string The gateway that the resulting orders must have.
     */
    public $gateway;

    /**
     * @var int The gateway ID that the resulting orders must have.
     */
    public $gatewayId;

    /**
     * @var User The user that the resulting orders must belong to.
     */
    public $user;

    /**
     * @var bool The payment status the resulting orders must belong to.
     */
    public $isPaid;

    /**
     * @var bool The payment status the resulting orders must belong to.
     */
    public $isUnpaid;

    /**
     * @var PurchasableInterface[] The resulting orders must contain these Purchasables.
     */
    public $hasPurchasables;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'commerce_orders.id';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'updatedAfter':
                $this->updatedAfter($value);
                break;
            case 'updatedBefore':
                $this->updatedBefore($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[number]] property.
     *
     * @param string $value The property value
     * @return static self reference
     */
    public function number($value)
    {
        $this->number = $value;

        return $this;
    }

    /**
     * Sets the [[email]] property.
     *
     * @param string $value The property value
     * @return static self reference
     */
    public function email(string $value)
    {
        $this->email = $value;

        return $this;
    }

    /**
     * Sets the [[isCompleted]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isCompleted(bool $value)
    {
        $this->isCompleted = $value;

        return $this;
    }

    /**
     * Sets the [[dateOrdered]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateOrdered($value)
    {
        $this->dateOrdered = $value;

        return $this;
    }

    /**
     * Sets the [[datePaid]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function datePaid($value)
    {
        $this->datePaid = $value;

        return $this;
    }

    /**
     * Sets the [[updatedOn]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function updatedOn($value)
    {
        $this->updatedOn = $value;

        return $this;
    }

    /**
     * Sets the [[expiryDate]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;

        return $this;
    }

    /**
     * Sets the [[updatedAfter]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function updatedAfter($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateUpdated = ArrayHelper::toArray($this->dateUpdated);
        $this->dateUpdated[] = '>=' . $value;

        return $this;
    }

    /**
     * Sets the [[updatedBefore]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function updatedBefore($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateUpdated = ArrayHelper::toArray($this->dateUpdated);
        $this->dateUpdated[] = '<' . $value;

        return $this;
    }

    /**
     * Sets the [[orderStatus]] property.
     *
     * @param OrderStatus|int $value The property value
     * @return static self reference
     */
    public function orderStatus($value)
    {
        if ($value instanceof OrderStatus) {
            $this->orderStatusId = $value->id;
        } else if ($value !== null) {
            $this->orderStatusId = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_orderstatuses}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->orderStatusId = null;
        }

        return $this;
    }

    /**
     * Sets the [[orderStatusId]] property.
     *
     * @param int $value The property value
     * @return static self reference
     */
    public function orderStatusId(int $value)
    {
        $this->orderStatusId = $value;

        return $this;
    }

    /**
     * Sets the [[customer]] property.
     *
     * @param Customer|int $value The property value
     * @return static self reference
     */
    public function customer($value)
    {
        if ($value instanceof Customer) {
            $this->customerId = $value->id;
        } else if ($value !== null) {
            $this->customerId = $value;
        } else {
            $this->customerId = null;
        }

        return $this;
    }

    /**
     * Sets the [[customerId]] property.
     *
     * @param int $value The property value
     * @return static self reference
     */
    public function customerId(int $value)
    {
        $this->customerId = $value;

        return $this;
    }

    /**
     * Sets the [[gateway]] property.
     *
     * @param Gateway|int $value The property value
     * @return static self reference
     */
    public function gateway($value)
    {
        if ($value instanceof Gateway) {
            $this->gatewayId = $value->id;
        } else if ($value !== null) {
            $this->gatewayId = $value;
        } else {
            $this->gatewayId = null;
        }

        return $this;
    }

    /**
     * Sets the [[gatewayId]] property.
     *
     * @param int $value The property value
     * @return static self reference
     */
    public function gatewayId(int $value)
    {
        $this->gatewayId = $value;

        return $this;
    }

    /**
     * Sets the [[user]] property.
     *
     * @param User|int $value The property value
     * @return static self reference
     */
    public function user($value)
    {
        if ($value instanceof User) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($value->id);
            $this->customerId = $customer->id ?? null;
        } else if ($value !== null) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($value);
            $this->customerId = $customer->id ?? null;
        } else {
            $this->customerId = null;
        }

        return $this;
    }

    /**
     * Sets the [[isPaid]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isPaid(bool $value)
    {
        $this->isPaid = $value;

        return $this;
    }

    /**
     * Sets the [[isUnpaid]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isUnpaid(bool $value)
    {
        $this->isUnpaid = $value;

        return $this;
    }

    /**
     * Sets the [[hasPurchasables]] property.
     *
     * @param PurchasableInterface|PurchasableInterface[] $value The property value
     * @return static self reference
     */
    public function hasPurchasables($value)
    {
        $this->hasPurchasables = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_orders');

        $this->query->select([
            'commerce_orders.id',
            'commerce_orders.number',
            'commerce_orders.couponCode',
            'commerce_orders.orderStatusId',
            'commerce_orders.dateOrdered',
            'commerce_orders.email',
            'commerce_orders.isCompleted',
            'commerce_orders.datePaid',
            'commerce_orders.currency',
            'commerce_orders.paymentCurrency',
            'commerce_orders.lastIp',
            'commerce_orders.orderLanguage',
            'commerce_orders.message',
            'commerce_orders.returnUrl',
            'commerce_orders.cancelUrl',
            'commerce_orders.billingAddressId',
            'commerce_orders.shippingAddressId',
            'commerce_orders.shippingMethodHandle',
            'commerce_orders.gatewayId',
            'commerce_orders.paymentSourceId',
            'commerce_orders.customerId',
            'commerce_orders.dateUpdated'
        ]);

        if ($this->number) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.number', $this->number));
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.email', $this->email));
        }

        if ($this->isCompleted) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.isCompleted', $this->isCompleted));
        }

        if ($this->dateOrdered) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateOrdered', $this->dateOrdered));
        }

        if ($this->datePaid) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.datePaid', $this->datePaid));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.expiryDate', $this->expiryDate));
        }

        if ($this->dateUpdated) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateUpdated', $this->dateUpdated));
        }

        if ($this->orderStatus) {
            if ($this->orderStatus instanceof OrderStatus) {
                $this->orderStatusId = $this->orderStatus->id;
                $this->orderStatus = null;
            } else if (is_int($this->orderStatus)) {
                $this->orderStatusId = $this->orderStatus;
                $this->orderStatus = null;
            }
        }

        if ($this->orderStatusId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderStatusId', $this->orderStatusId));
        }

        if ($this->customer) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.customer', $this->customer));
        }

        if ($this->customerId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.customerId', $this->customerId));
        }

        if ($this->gatewayId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.gatewayId', $this->gatewayId));
        }

        if ($this->user) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.user', $this->user));
        }

        if ($this->isPaid) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalPaid', '>= commerce_orders.totalPrice'));
        }

        if ($this->isUnpaid) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalPaid', '< commerce_orders.totalPrice'));
        }

        if ($this->hasPurchasables) {
            $purchasableIds = [];

            if (is_array($this->hasPurchasables) !== true) {
                $this->hasPurchasables = [$this->hasPurchasables];
            }

            foreach ($this->hasPurchasables as $purchasable) {
                if ($purchasable instanceof PurchasableInterface) {
                    $purchasableIds[] = $purchasable->getId();
                }

                if (is_numeric($purchasable)) {
                    $purchasableIds[] = $purchasable;
                }
            }

            // Remove any blank purchasable IDs (if any)
            $purchasableIds = array_filter($purchasableIds);

            $this->subQuery->innerJoin('{{%commerce_lineitems}} lineitems', '[[lineitems.orderId]] = [[commerce_orders.id]]');
            $this->subQuery->andWhere(['in', '[[lineitems.purchasableId]]', $purchasableIds]);
        }

        return parent::beforePrepare();
    }
}
