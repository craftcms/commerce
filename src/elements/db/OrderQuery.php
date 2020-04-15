<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Schema;

/**
 * OrderQuery represents a SELECT SQL statement for orders in a way that is independent of DBMS.
 *
 * @method Order[]|array all($db = null)
 * @method Order|array|null one($db = null)
 * @method Order|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @replace {element} order
 * @replace {elements} orders
 * @replace {twig-method} craft.orders()
 * @replace {myElement} myOrder
 * @replace {element-class} \craft\commerce\elements\Order
 */
class OrderQuery extends ElementQuery
{
    /**
     * @var string The order number of the resulting order.
     */
    public $number;

    /**
     * @var string The short order number of the resulting order.
     */
    public $shortNumber;

    /**
     * @var string The order reference of the resulting order.
     * @used-by reference()
     */
    public $reference;

    /**
     * @var string The email address the resulting orders must have.
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
     * @var mixed The Expiry Date that the resulting orders must have.
     */
    public $expiryDate;

    /**
     * @var mixed The date the order was paid in full.
     */
    public $datePaid;

    /**
     * @var mixed The date the order was authorized in full.
     */
    public $dateAuthorized;

    /**
     * @var int The Order Status ID that the resulting orders must have.
     */
    public $orderStatusId;

    /**
     * @var string|null The origin the resulting orders must have.
     */
    public $origin;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public $customerId;

    /**
     * @var int The gateway ID that the resulting orders must have.
     */
    public $gatewayId;

    /**
     * @var bool Whether the order is paid
     */
    public $isPaid;

    /**
     * @var bool Whether the order is unpaid
     */
    public $isUnpaid;

    /**
     * @var PurchasableInterface|PurchasableInterface[] The resulting orders must contain these Purchasables.
     */
    public $hasPurchasables;

    /**
     * @var bool Whether the order has any transactions
     */
    public $hasTransactions;

    /**
     * @var bool Whether the order has any line items.
     */
    public $hasLineItems;

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['commerce_orders.id' => SORT_ASC];

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
     * Narrows the query results based on the order number.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'` | with a matching order number
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set orderNumber = craft.app.request.getQueryParam('number') %}
     * {% set {element-var} = {twig-method}
     *     .number(orderNumber)
     *     .one() %}
     * ```
     *
     * ```php
     * // Fetch the requested {element}
     * $orderNumber = Craft::$app->request->getQueryParam('number');
     * ${element-var} = {php-method}
     *     ->number($orderNumber)
     *     ->one();
     * ```
     *
     * @param string|array|null $value The property value.
     * @return static self reference
     */
    public function number($value = null)
    {
        $this->number = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order short number.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'xxxxxxx'` | with a matching order number
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set orderNumber = craft.app.request.getQueryParam('shortNumber') %}
     * {% set {element-var} = {twig-method}
     *     .shortNumber(orderNumber)
     *     .one() %}
     * ```
     *
     * ```php
     * // Fetch the requested {element}
     * $orderNumber = Craft::$app->request->getQueryParam('shortNumber');
     * ${element-var} = {php-method}
     *     ->shortNumber($orderNumber)
     *     ->one();
     * ```
     *
     * @param string|array|null $value The property value.
     * @return static self reference
     * @since 2.2
     */
    public function shortNumber($value = null)
    {
        $this->shortNumber = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order reference.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'xxxx'` | with a matching order reference
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set orderReference = craft.app.request.getQueryParam('ref') %}
     * {% set {element-var} = {twig-method}
     *     .reference(orderReference)
     *     .one() %}
     * ```
     *
     * ```php
     * // Fetch the requested {element}
     * $orderReference = Craft::$app->request->getQueryParam('ref');
     * ${element-var} = {php-method}
     *     ->reference($orderReference)
     *     ->one();
     * ```
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function reference(string $value = null)
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the customers’ email addresses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements} with customers…
     * | - | -
     * | `'foo@bar.baz'` | with an email of `foo@bar.baz`.
     * | `'not foo@bar.baz'` | not with an email of `foo@bar.baz`.
     * | `'*@bar.baz'` | with an email that ends with `@bar.baz`.
     *
     * ---
     *
     * ```twig
     * {# Fetch orders from customers with a .co.uk domain on their email address #}
     * {% set {elements-var} = {twig-method}
     *     .email('*.co.uk')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch orders from customers with a .co.uk domain on their email address
     * ${elements-var} = {php-method}
     *     ->email('*.co.uk')
     *     ->all();
     * ```
     *
     * @param string|string[]|null $value The property value
     * @return static self reference
     */
    public function email($value)
    {
        $this->email = $value;
        return $this;
    }

    /**
     * Narrows the query results to only orders that are completed.
     *
     * ---
     *
     * ```twig
     * {# Fetch completed orders #}
     * {% set {elements-var} = {twig-function}
     *     .isCompleted()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch completed orders
     * ${elements-var} = {element-class}::find()
     *     ->isCompleted()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isCompleted(bool $value = true)
    {
        $this->isCompleted = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the orders’ completion dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were completed on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were completed before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were completed between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were completed recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .dateOrdered(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were completed recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateOrdered(">= {$aWeekAgo}")
     *     ->all();
     * ```
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
     * Narrows the query results based on the orders’ paid dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were paid on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were paid before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were completed between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were paid for recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .datePaid(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were paid for recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->datePaid(">= {$aWeekAgo}")
     *     ->all();
     * ```
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
     * Narrows the query results based on the orders’ authorized dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were authorized on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were authorized before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were completed between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were authorized recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .dateAuthorized(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were authorized recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateAuthorized(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateAuthorized($value)
    {
        $this->dateAuthorized = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the orders’ expiry dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2020-04-01'` | that will expire on or after 2020-04-01.
     * | `'< 2020-05-01'` | that will expire before 2020-05-01
     * | `['and', '>= 2020-04-04', '< 2020-05-01']` | that will expire between 2020-04-01 and 2020-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} expiring this month #}
     * {% set nextMonth = date('first day of next month')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .expiryDate("< #{nextMonth}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} expiring this month
     * $nextMonth = new \DateTime('first day of next month')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->expiryDate("< {$nextMonth}")
     *     ->all();
     * ```
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
     * Narrows the query results based on the order statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | with an order status with a handle of `foo`.
     * | `'not foo'` | not with an order status with a handle of `foo`.
     * | `['foo', 'bar']` | with an order status with a handle of `foo` or `bar`.
     * | `['not', 'foo', 'bar']` | not with an order status with a handle of `foo` or `bar`.
     * | a [[OrderStatus|OrderStatus]] object | with an order status represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch shipped {elements} #}
     * {% set {elements-var} = {twig-method}
     *     .orderStatus('shipped')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch shipped {elements}
     * ${elements-var} = {php-method}
     *     ->orderStatus('shipped')
     *     ->all();
     * ```
     *
     * @param string|string[]|OrderStatus|null $value The property value
     * @return static self reference
     */
    public function orderStatus($value)
    {
        if ($value instanceof OrderStatus) {
            $this->orderStatusId = $value->id;
        } else if ($value !== null) {
            $this->orderStatusId = (new Query())
                ->select(['id'])
                ->from([Table::ORDERSTATUSES])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->orderStatusId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the order statuses, per their IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with an order status with an ID of 1.
     * | `'not 1'` | not with an order status with an ID of 1.
     * | `[1, 2]` | with an order status with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with an order status with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with an order status with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *     .authorGroupId(1)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with an order status with an ID of 1
     * ${elements-var} = {php-method}
     *     ->authorGroupId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function orderStatusId($value)
    {
        $this->orderStatusId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the origin.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'web'` | with an origin of `web`.
     * | `'not remote'` | not with an origin of `remote`.
     * | `['web', 'cp']` | with an order origin of `web` or `cp`.
     * | `['not', 'remote', 'cp']` | not with an origin of `web` or `cp`.
     *
     * ---
     *
     * ```twig
     * {# Fetch shipped {elements} #}
     * {% set {elements-var} = {twig-method}
     *     .origin('web')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch shipped {elements}
     * ${elements-var} = {php-method}
     *     ->origin('web')
     *     ->all();
     * ```
     *
     * @param string|string[]|null $value The property value
     * @return static self reference
     */
    public function origin($value = null)
    {
        $this->origin = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the customer.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[Customer|Customer]] object | with a customer represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's orders #}
     * {% set {elements-var} = {twig-method}
     *     .customer(currentUser.customerFieldHandle)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's orders
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->customer($user->customerFieldHandle)
     *     ->all();
     * ```
     *
     * @param Customer|null $value The property value
     * @return static self reference
     */
    public function customer(Customer $value = null)
    {
        if ($value) {
            $this->customerId = $value->id;
        } else {
            $this->customerId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the customer, per their ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with a customer with an ID of 1.
     * | `'not 1'` | not with a customer with an ID of 1.
     * | `[1, 2]` | with a customer with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with a customer with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's orders #}
     * {% set {elements-var} = {twig-method}
     *     .customerId(currentUser.customerFieldHandle.id)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's orders
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->customerId($user->customerFieldHandle->id)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function customerId($value)
    {
        $this->customerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the gateway.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[Gateway|Gateway]] object | with a gateway represented by the object.
     *
     * @param GatewayInterface|null $value The property value
     * @return static self reference
     */
    public function gateway(GatewayInterface $value = null)
    {
        if ($value) {
            /** @var Gateway $value */
            $this->gatewayId = $value->id;
        } else {
            $this->gatewayId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the gateway, per its ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with a gateway with an ID of 1.
     * | `'not 1'` | not with a gateway with an ID of 1.
     * | `[1, 2]` | with a gateway with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with a gateway with an ID of 1 or 2.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function gatewayId($value)
    {
        $this->gatewayId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the customer’s user account.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with a customer with a user account ID of 1.
     * | a [[User|User]] object | with a customer with a user account represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's orders #}
     * {% set {elements-var} = {twig-method}
     *     .user(currentUser)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's orders
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->user($user)
     *     ->all();
     * ```
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
     * Narrows the query results to only orders that are paid.
     *
     * ---
     *
     * ```twig
     * {# Fetch paid orders #}
     * {% set {elements-var} = {twig-function}
     *     .isPaid()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch paid orders
     * ${elements-var} = {element-class}::find()
     *     ->isPaid()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isPaid(bool $value = true)
    {
        $this->isPaid = $value;
        return $this;
    }

    /**
     * Narrows the query results to only orders that are not paid.
     *
     * ---
     *
     * ```twig
     * {# Fetch unpaid orders #}
     * {% set {elements-var} = {twig-function}
     *     .isUnpaid()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch unpaid orders
     * ${elements-var} = {element-class}::find()
     *     ->isUnpaid()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isUnpaid(bool $value = true)
    {
        $this->isUnpaid = $value;
        return $this;
    }

    /**
     * Narrows the query results to only orders that have line items.
     *
     * ---
     *
     * ```twig
     * {# Fetch orders that do or do not have line items #}
     * {% set {elements-var} = {twig-function}
     *     .hasLineItems()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch unpaid orders
     * ${elements-var} = {element-class}::find()
     *     ->hasLineItems()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function hasLineItems(bool $value = true)
    {
        $this->hasLineItems = $value;
        return $this;
    }

    /**
     * Narrows the query results to only carts that have at least one transaction.
     *
     * ---
     *
     * ```twig
     * {# Fetch carts that have attempted payments #}
     * {% set {elements-var} = {twig-function}
     *     .hasTransactions()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch carts that have attempted payments
     * ${elements-var} = {element-class}::find()
     *     ->hasTransactions()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function hasTransactions(bool $value = true)
    {
        $this->hasTransactions = $value;
        return $this;
    }

    /**
     * Narrows the query results to only orders that have certain purchasables.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[PurchasableInterface|PurchasableInterface]] object | with a purchasable represented by the object.
     * | an array of [[PurchasableInterface|PurchasableInterface]] objects | with all the purchasables represented by the objects.
     *
     * @param PurchasableInterface|PurchasableInterface[]|null $value The property value
     * @return static self reference
     */
    public function hasPurchasables($value)
    {
        $this->hasPurchasables = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_orders');

        $this->query->select([
            'commerce_orders.id',
            'commerce_orders.number',
            'commerce_orders.reference',
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
            'commerce_orders.estimatedBillingAddressId',
            'commerce_orders.estimatedShippingAddressId',
            'commerce_orders.shippingMethodHandle',
            'commerce_orders.gatewayId',
            'commerce_orders.paymentSourceId',
            'commerce_orders.customerId',
            'commerce_orders.dateUpdated',
        ]);

        // Join shipping and billing address
        $this->query->leftJoin(Table::ADDRESSES . ' billing_address', '[[billing_address.id]] = [[commerce_orders.billingAddressId]]');
        $this->subQuery->leftJoin(Table::ADDRESSES . ' billing_address', '[[billing_address.id]] = [[commerce_orders.billingAddressId]]');
        $this->query->leftJoin(Table::ADDRESSES . ' shipping_address', '[[shipping_address.id]] = [[commerce_orders.shippingAddressId]]');
        $this->subQuery->leftJoin(Table::ADDRESSES . ' shipping_address', '[[shipping_address.id]] = [[commerce_orders.shippingAddressId]]');

        // TODO: remove after next breakpoint
        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');

        if ($commerce && version_compare($commerce['version'], '2.1.3', '>=')) {
            $this->query->addSelect(['commerce_orders.registerUserOnOrderComplete']);
        }

        if ($commerce && version_compare($commerce['version'], '3.0', '>=')) {
            $this->query->addSelect(['commerce_orders.recalculationMode']);
            $this->query->addSelect(['commerce_orders.origin']);

            if ($this->origin) {
                $this->subQuery->andWhere(Db::parseParam('commerce_orders.origin', $this->origin));
            }
        }

        if ($commerce && version_compare($commerce['version'], '3.0.6', '>=')) {
            $this->query->addSelect(['commerce_orders.dateAuthorized']);
            if ($this->dateAuthorized) {
                $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateAuthorized', $this->datePaid));
            }
        }

        if ($commerce && version_compare($commerce['version'], '3.0.7', '>=')) {
            $this->query->addSelect([
                'storedTotalPrice' => 'commerce_orders.totalPrice',
                'storedTotalPaid' => 'commerce_orders.totalPaid',
                'storedItemTotal' => 'commerce_orders.itemTotal',
                'storedTotalDiscount' => 'commerce_orders.totalDiscount',
                'storedTotalShippingCost' => 'commerce_orders.totalShippingCost',
                'storedTotalTax' => 'commerce_orders.totalTax',
                'storedTotalTaxIncluded' => 'commerce_orders.totalTaxIncluded',
            ]);
        }

        if ($this->number !== null) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->number) || $this->number === '') {
                return false;
            }
            $this->subQuery->andWhere(['commerce_orders.number' => $this->number]);
        }

        if ($this->shortNumber !== null) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->shortNumber) || $this->shortNumber === '') {
                return false;
            }

            $this->subQuery->andWhere(new Expression('LEFT([[commerce_orders.number]], 7) = :shortNumber', [':shortNumber' => $this->shortNumber]));
        }

        if ($this->reference) {
            $this->subQuery->andWhere(['commerce_orders.reference' => $this->reference]);
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.email', $this->email));
        }

        // Allow true ot false but not null
        if ($this->isCompleted !== null) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.isCompleted', $this->isCompleted, '=', false, Schema::TYPE_BOOLEAN));
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

        if ($this->orderStatusId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderStatusId', $this->orderStatusId));
        }

        if ($this->customerId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.customerId', $this->customerId));
        }

        if ($this->gatewayId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.gatewayId', $this->gatewayId));
        }

        // Allow true ot false but not null
        if (($this->isPaid !== null) && $this->isPaid) {
            $this->subQuery->andWhere(new Expression('[[commerce_orders.totalPaid]] >= [[commerce_orders.totalPrice]]'));
        }

        // Allow true or false but not null
        if (($this->isUnpaid !== null) && $this->isUnpaid) {
            $this->subQuery->andWhere(new Expression('[[commerce_orders.totalPaid]] < [[commerce_orders.totalPrice]]'));
        }

        // Allow true ot false but not null
        if (($this->hasPurchasables !== null) && $this->hasPurchasables) {
            $purchasableIds = [];

            if (!is_array($this->hasPurchasables)) {
                $this->hasPurchasables = [$this->hasPurchasables];
            }

            foreach ($this->hasPurchasables as $purchasable) {
                if ($purchasable instanceof PurchasableInterface) {
                    $purchasableIds[] = $purchasable->getId();
                } else if (is_numeric($purchasable)) {
                    $purchasableIds[] = $purchasable;
                }
            }

            // Remove any blank purchasable IDs (if any)
            $purchasableIds = array_filter($purchasableIds);

            $this->subQuery->innerJoin(Table::LINEITEMS . ' lineitems', '[[lineitems.orderId]] = [[commerce_orders.id]]');
            $this->subQuery->andWhere(['lineitems.purchasableId' => $purchasableIds]);
        }

        // Allow true or false but not null
        if (($this->hasTransactions !== null) && $this->hasTransactions) {
            $this->subQuery->leftJoin(Table::TRANSACTIONS . ' transactions', '[[transactions.orderId]] = [[commerce_orders.id]]');
            $this->subQuery->andWhere(['not', ['transactions.id' => null]]);
        }

        // Allow true or false but not null
        if (($this->hasLineItems !== null) && $this->hasLineItems) {
            $this->subQuery->leftJoin(Table::LINEITEMS . ' lineItems', '[[lineItems.orderId]] = [[commerce_orders.id]]');
            $this->subQuery->andWhere(['not', ['lineItems.id' => null]]);
        }

        return parent::beforePrepare();
    }
}
