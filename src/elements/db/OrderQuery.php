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
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\Db;
use yii\db\Connection;
use yii\db\Expression;

/**
 * OrderQuery represents a SELECT SQL statement for orders in a way that is independent of DBMS.
 *
 * @method Order[]|array all($db = null)
 * @method Order|array|null one($db = null)
 * @method Order|array|null nth(int $n, Connection $db = null)
 * @method self id($value)
 * @method self orderBy($columns)
 * @method self status(array|string|null $value)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @doc-path orders-carts.md
 * @replace {element} order
 * @replace {elements} orders
 * @replace {twig-method} craft.orders()
 * @replace {myElement} myOrder
 * @replace {element-class} \craft\commerce\elements\Order
 */
class OrderQuery extends ElementQuery
{
    /**
     * @var mixed The order number of the resulting order.
     */
    public mixed $number = null;

    /**
     * @var mixed The short order number of the resulting order.
     */
    public mixed $shortNumber = null;

    /**
     * @var mixed The order reference of the resulting order.
     * @used-by reference()
     */
    public mixed $reference = null;

    /**
     * @var mixed The order reference of the resulting order.
     * @used-by couponCode()
     */
    public mixed $couponCode = null;

    /**
     * @var mixed The email address the resulting orders must have.
     */
    public mixed $email = null;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public ?bool $isCompleted = null;

    /**
     * @var mixed The Date Ordered date that the resulting orders must have.
     */
    public mixed $dateOrdered = null;

    /**
     * @var mixed The Expiry Date that the resulting orders must have.
     */
    public mixed $expiryDate = null;

    /**
     * @var mixed The date the order was paid in full.
     */
    public mixed $datePaid = null;

    /**
     * @var mixed The date the order was authorized in full.
     */
    public mixed $dateAuthorized = null;

    /**
     * @var mixed The Order Status ID that the resulting orders must have.
     */
    public mixed $orderStatusId = null;

    /**
     * @var mixed The language the order was made that the resulting the order must have.
     */
    public mixed $orderLanguage = null;

    /**
     * @var mixed The Order Site ID that the resulting orders must have.
     */
    public mixed $orderSiteId = null;

    /**
     * @var mixed The origin the resulting orders must have.
     */
    public mixed $origin = null;

    /**
     * @var mixed The user ID that the resulting orders must have.
     */
    public mixed $customerId = null;

    /**
     * @var mixed The gateway ID that the resulting orders must have.
     */
    public mixed $gatewayId = null;

    /**
     * @var int|null The store ID that the resulting orders must have.
     */
    public ?int $storeId = null;

    /**
     * @var mixed The total of the order resulting orders must have.
     * @since 4.2.0
     */
    public mixed $total = null;

    /**
     * @var mixed The total price of the order resulting orders must have.
     * @since 4.2.0
     */
    public mixed $totalPrice = null;

    /**
     * @var mixed The total paid amount of the order resulting orders must have.
     * @since 4.2.0
     */
    public mixed $totalPaid = null;

    /**
     * @var mixed The total qty of the order resulting orders must have.
     * @since 4.2.0
     */
    public mixed $totalQty = null;

    /**
     * @var mixed The total weight of the order resulting orders must have.
     * @since 5.0.0
     */
    public mixed $totalWeight = null;

    /**
     * @var mixed The total discount of the order resulting orders must have.
     * @since 4.2.0
     */
    public mixed $totalDiscount = null;

    /**
     * @var mixed The total tax resulting orders must have.
     * @since 4.2.0
     */
    public mixed $totalTax = null;

    /**
     * @var mixed The total price of the items resulting orders must have.
     * @since 4.2.0
     */
    public mixed $itemTotal = null;

    /**
     * @var mixed The subtotal price of the items resulting orders must have.
     * @since 4.2.0
     */
    public mixed $itemSubtotal = null;

    /**
     * @var mixed The shipping method handle the resulting orders must have.
     * @since 4.2.0
     */
    public mixed $shippingMethodHandle = null;

    /**
     * @var bool|null Whether the order is paid
     */
    public ?bool $isPaid = null;

    /**
     * @var bool|null Whether the order is unpaid
     */
    public ?bool $isUnpaid = null;

    /**
     * @var mixed The resulting orders must contain these Purchasables.
     */
    public mixed $hasPurchasables = null;

    /**
     * @var bool|null Whether the order has any transactions
     */
    public ?bool $hasTransactions = null;

    /**
     * @var bool|null Whether the order has any line items.
     */
    public ?bool $hasLineItems = null;

    /**
     * @var bool Eager loads all relational data (addresses, adjustments, users, line items, transactions) for the resulting orders.
     */
    public bool $withAll = false;

    /**
     * @var bool Eager loads the shipping and billing addressees on the resulting orders.
     */
    public bool $withAddresses = false;

    /**
     * @var bool Eager loads the order adjustments on the resulting orders.
     */
    public bool $withAdjustments = false;

    /**
     * @var bool Eager load the user on to the order.
     */
    public bool $withCustomer = false;

    /**
     * @var bool Eager loads the line items on the resulting orders.
     */
    public bool $withLineItems = false;

    /**
     * @var bool Eager loads the transactions on the resulting orders.
     */
    public bool $withTransactions = false;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['commerce_orders.id' => SORT_ASC];

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
     *   .number(orderNumber)
     *   .one() %}
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
    public function number(mixed $value): OrderQuery
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
     *   .shortNumber(orderNumber)
     *   .one() %}
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
    public function shortNumber(mixed $value): OrderQuery
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
     * | `'Foo'` | with a reference of `Foo`.
     * | `'Foo*'` | with a reference that begins with `Foo`.
     * | `'*Foo'` | with a reference that ends with `Foo`.
     * | `'*Foo*'` | with a reference that contains `Foo`.
     * | `'not *Foo*'` | with a reference that doesn’t contain `Foo`.
     * | `['*Foo*', '*Bar*']` | with a reference that contains `Foo` or `Bar`.
     * | `['not', '*Foo*', '*Bar*']` | with a reference that doesn’t contain `Foo` or `Bar`.
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set orderReference = craft.app.request.getQueryParam('ref') %}
     * {% set {element-var} = {twig-method}
     *   .reference(orderReference)
     *   .one() %}
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
    public function reference(mixed $value): OrderQuery
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order's coupon code.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `':empty:'` | that don’t have a coupon code.
     * | `':notempty:'` | that have a coupon code.
     * | `'Foo'` | with a coupon code of `Foo`.
     * | `'Foo*'` | with a coupon code that begins with `Foo`.
     * | `'*Foo'` | with a coupon code that ends with `Foo`.
     * | `'*Foo*'` | with a coupon code that contains `Foo`.
     * | `'not *Foo*'` | with a coupon code that doesn’t contain `Foo`.
     * | `['*Foo*', '*Bar*']` | with a coupon code that contains `Foo` or `Bar`.
     * | `['not', '*Foo*', '*Bar*']` | with a coupon code that doesn’t contain `Foo` or `Bar`.
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set {element-var} = {twig-method}
     *   .reference('foo')
     *   .one() %}
     * ```
     *
     * ```php
     * // Fetch the requested {element}
     * ${element-var} = {php-method}
     *     ->reference('foo')
     *     ->one();
     * ```
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function couponCode(mixed $value): OrderQuery
    {
        $this->couponCode = $value;
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
     *   .email('*.co.uk')
     *   .all() %}
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
    public function email(mixed $value): OrderQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isCompleted()
     *   .all() %}
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
    public function isCompleted(?bool $value = true): OrderQuery
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
     *   .dateOrdered(">= #{aWeekAgo}")
     *   .all() %}
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
    public function dateOrdered(mixed $value): OrderQuery
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
     *   .datePaid(">= #{aWeekAgo}")
     *   .all() %}
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
    public function datePaid(mixed $value): OrderQuery
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
     *   .dateAuthorized(">= #{aWeekAgo}")
     *   .all() %}
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
    public function dateAuthorized(mixed $value): OrderQuery
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
     *   .expiryDate("< #{nextMonth}")
     *   .all() %}
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
    public function expiryDate(mixed $value): OrderQuery
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
     *   .orderStatus('shipped')
     *   .all() %}
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
    public function orderStatus(mixed $value): OrderQuery
    {
        if ($value instanceof OrderStatus) {
            $this->orderStatusId = $value->id;
        } elseif ($value !== null) {
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
     * Narrows the query results based on the shipping method handle.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | with a shipping method with a handle of `foo`.
     * | `'not foo'` | not with a shipping method with a handle of `foo`.
     * | `['foo', 'bar']` | with a shipping method with a handle of `foo` or `bar`.
     * | `['not', 'foo', 'bar']` | not with a shipping method with a handle of `foo` or `bar`.
     * | a [[ShippingMethod|ShippingMethod]] object | with a shipping method represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch collection shipping method {elements} #}
     * {% set {elements-var} = {twig-method}
     *   .shippingMethodHandle('collection')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch collection shipping method {elements}
     * ${elements-var} = {php-method}
     *     ->shippingMethodHandle('collection')
     *     ->all();
     * ```
     *
     * @param string|string[]|null $value The property value
     * @return static self reference
     */
    public function shippingMethodHandle(mixed $value): OrderQuery
    {
        $this->shippingMethodHandle = $value;
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
     *   .orderStatusId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with an order status with an ID of 1
     * ${elements-var} = {php-method}
     *     ->orderStatusId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function orderStatusId(mixed $value): OrderQuery
    {
        $this->orderStatusId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order language, per the language string provided.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'en'` | with an order language that is `'en'`.
     * | `'not en'` | not with an order language that is not `'en'`.
     * | `['en', 'en-us']` | with an order language that is `'en'` or `'en-us'`.
     * | `['not', 'en']` | not with an order language that is not `'en'`.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with an order language that is `'en'` #}
     * {% set {elements-var} = {twig-method}
     *   .orderLanguage('en')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with an order language that is `'en'`
     * ${elements-var} = {php-method}
     *     ->orderLanguage('en')
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function orderLanguage(mixed $value): OrderQuery
    {
        $this->orderLanguage = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order language, per the language string provided.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with an order site ID of 1.
     * | `'not 1'` | not with an order site ID that is no 1.
     * | `[1, 2]` | with an order site ID of 1 or 2.
     * | `['not', 1, 2]` | not with an order site ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with an order site ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .orderSiteId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with an order site ID of 1
     * ${elements-var} = {php-method}
     *     ->orderSiteId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function orderSiteId(mixed $value): OrderQuery
    {
        $this->orderSiteId = $value;
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
     *   .origin('web')
     *   .all() %}
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
    public function origin(mixed $value): OrderQuery
    {
        $this->origin = $value;

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
    public function gateway(?GatewayInterface $value): OrderQuery
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
    public function gatewayId(mixed $value): OrderQuery
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
     *   .user(currentUser)
     *   .all() %}
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
     * @param User|int|null $value The property value
     * @return static self reference
     * @deprecated 4.0.0 in favor of [[customer()]]
     */
    public function user(int|User|null $value): OrderQuery
    {
        Craft::$app->getDeprecator()->log('OrderQuery::user()', 'The `OrderQuery::user()` method is deprecated, use the `OrderQuery::customer()` method instead.');
        return $this->customer($value);
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
     * | `'not 1'` | not the user account with an ID 1.
     * | `[1, 2]` | with an user account ID of 1 or 2.
     * | `['not', 1, 2]` | not with a user account ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's orders #}
     * {% set {elements-var} = {twig-method}
     *   .customer(currentUser)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's orders
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->customer($user)
     *     ->all();
     * ```
     *
     * @param User|int|null $value The property value
     * @return static self reference
     */
    public function customer(int|User|null $value): OrderQuery
    {
        if ($value instanceof User) {
            $this->customerId = $value->id;
        } else {
            $this->customerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the customer, per their user ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with a user with an ID of 1.
     * | `'not 1'` | not with a user with an ID of 1.
     * | `[1, 2]` | with a user with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with a user with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's orders #}
     * {% set {elements-var} = {twig-method}
     *   .customerId(currentUser.id)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's orders
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->customerId($user->id)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function customerId(mixed $value): OrderQuery
    {
        $this->customerId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total price of $10.
     * | `['and', 10, 20]` | an order with a total of $10 or $20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function total(mixed $value): OrderQuery
    {
        $this->total = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total price of $10.
     * | `['and', 10, 20]` | an order with a total price of $10 or $20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalPrice(mixed $value): OrderQuery
    {
        $this->totalPrice = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total paid amount.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total paid amount of $10.
     * | `['and', 10, 20]` | an order with a total paid amount of $10 or $20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalPaid(mixed $value): OrderQuery
    {
        $this->totalPaid = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total qty of items.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total qty of 10.
     * | `[10, 20]` | an order with a total qty of 10 or 20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalQty(mixed $value): OrderQuery
    {
        $this->totalQty = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total weight of items.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total weight of 10.
     * | `[10, 20]` | an order with a total weight of 10 or 20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalWeight(mixed $value): OrderQuery
    {
        $this->totalWeight = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total discount.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total discount of 10.
     * | `[10, 20]` | an order with a total discount of 10 or 20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalDiscount(mixed $value): OrderQuery
    {
        $this->totalDiscount = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the total tax.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | with a total tax of 10.
     * | `[10, 20]` | an order with a total tax of 10 or 20.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function totalTax(mixed $value): OrderQuery
    {
        $this->totalTax = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order’s item total.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with an item total of $100.
     * | `'< 1000000'` | with an item total of less than $1,000,000.
     * | `['>= 10', '< 100']` | with an item total of between $10 and $100.

     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function itemTotal(mixed $value): OrderQuery
    {
        $this->itemTotal = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the order’s item subtotal.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `100` | with an item subtotal of $100.
     * | `'< 1000000'` | with an item subtotal of less than $1,000,000.
     * | `['>= 10', '< 100']` | with an item subtotal of between $10 and $100.

     *
     * @param mixed $value The property value
     * @return static self reference
     * @since 4.2.0
     */
    public function itemSubtotal(mixed $value): OrderQuery
    {
        $this->itemSubtotal = $value;
        return $this;
    }

    /**
     * Narrows the query results to only orders that are paid.
     *
     * ---
     *
     * ```twig
     * {# Fetch paid orders #}
     * {% set {elements-var} = {twig-method}
     *   .isPaid()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch paid orders
     * ${elements-var} = {element-class}::find()
     *     ->isPaid()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isPaid(?bool $value = true): OrderQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isUnpaid()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch unpaid orders
     * ${elements-var} = {element-class}::find()
     *     ->isUnpaid()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isUnpaid(?bool $value = true): OrderQuery
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
     * {% set {elements-var} = {twig-method}
     *   .hasLineItems()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch unpaid orders
     * ${elements-var} = {element-class}::find()
     *     ->hasLineItems()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function hasLineItems(?bool $value = true): OrderQuery
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
     * {% set {elements-var} = {twig-method}
     *   .hasTransactions()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch carts that have attempted payments
     * ${elements-var} = {element-class}::find()
     *     ->hasTransactions()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function hasTransactions(?bool $value = true): OrderQuery
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
     * @param PurchasableInterface|array<int, (int|PurchasableInterface)>|null $value The property value
     * @return static self reference
     */
    public function hasPurchasables(mixed $value): OrderQuery
    {
        $this->hasPurchasables = $value;

        return $this;
    }

    /**
     * Narrows the query results to only orders that are related to the given store.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with a `storeId` of `1`.
     *
     * @param int|null $value
     * @return static self reference
     */
    public function storeId(?int $value): OrderQuery
    {
        $this->storeId = $value;

        return $this;
    }

    /**
     * Eager loads all relational data (addresses, adjustments, customers, line items, transactions) for the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses, adjustments, customers, line items, transactions
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withAll()
     */
    public function withAll(bool $value = true): OrderQuery
    {
        $this->withAll = $value;

        return $this;
    }

    /**
     * Eager loads the shipping and billing addressees on the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches addresses
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withAddresses()
     */
    public function withAddresses(bool $value = true): OrderQuery
    {
        $this->withAddresses = $value;

        return $this;
    }

    /**
     * Eager loads the order adjustments on the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches adjustments
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withAdjustments()
     */
    public function withAdjustments(bool $value = true): OrderQuery
    {
        $this->withAdjustments = $value;

        return $this;
    }

    /**
     * Eager loads the user on the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches adjustments
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withCustomer()
     */
    public function withCustomer(bool $value = true): OrderQuery
    {
        $this->withCustomer = $value;

        return $this;
    }

    /**
     * Eager loads the line items on the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches line items
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withLineItems()
     */
    public function withLineItems(bool $value = true): OrderQuery
    {
        $this->withLineItems = $value;

        return $this;
    }

    /**
     * Eager loads the transactions on the resulting orders.
     *
     * Possible values include:
     *
     * | Value | Fetches transactions…
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withTransactions()
     */
    public function withTransactions(bool $value = true): OrderQuery
    {
        $this->withTransactions = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function populate($rows): array
    {
        // @TODO remove at next breaking change
        // Remove `email` key from each row.
        array_walk($rows, function(&$row) {
            if (array_key_exists('email', $row)) {
                unset($row['email']);
            }
        });

        /** @var Order[] $orders */
        $orders = parent::populate($rows);

        // Eager-load anything?
        if (!empty($orders) && !$this->asArray) {

            // Eager-load line items?
            if ($this->withLineItems === true || $this->withAll) {
                $orders = Plugin::getInstance()->getLineItems()->eagerLoadLineItemsForOrders($orders);
            }

            // Eager-load transactions?
            if ($this->withTransactions === true || $this->withAll) {
                $orders = Plugin::getInstance()->getTransactions()->eagerLoadTransactionsForOrders($orders);
            }

            // Eager-load adjustments?
            if ($this->withAdjustments === true || $this->withAll) {
                $orders = Plugin::getInstance()->getOrderAdjustments()->eagerLoadOrderAdjustmentsForOrders($orders);
            }

            // Eager-load users?
            if ($this->withCustomer === true || $this->withAll) {
                $orders = Plugin::getInstance()->getCustomers()->eagerLoadCustomerForOrders($orders);
            }

            // Eager-load addresses?
            if ($this->withAddresses === true || $this->withAll) {
                $orders = Plugin::getInstance()->getOrders()->eagerLoadAddressesForOrders($orders);
            }

            $orders = Plugin::getInstance()->getOrderNotices()->eagerLoadOrderNoticesForOrders($orders);
        }

        return $orders;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_orders');

        $this->query->select([
            'commerce_orders.id',
            'commerce_orders.storeId',
            'commerce_orders.number',
            'commerce_orders.reference',
            'commerce_orders.couponCode',
            'commerce_orders.orderStatusId',
            'commerce_orders.dateOrdered',

            // @TODO remove at next breaking change
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
            'commerce_orders.sourceBillingAddressId',
            'commerce_orders.sourceShippingAddressId',
            'commerce_orders.shippingMethodHandle',
            'commerce_orders.gatewayId',
            'commerce_orders.paymentSourceId',
            'commerce_orders.customerId',
            'commerce_orders.dateUpdated',
            'commerce_orders.registerUserOnOrderComplete',
            'commerce_orders.saveBillingAddressOnOrderComplete',
            'commerce_orders.saveShippingAddressOnOrderComplete',
            'commerce_orders.saveShippingAddressOnOrderComplete',
            'commerce_orders.makePrimaryShippingAddress',
            'commerce_orders.makePrimaryBillingAddress',
            'commerce_orders.recalculationMode',
            'commerce_orders.origin',
            'commerce_orders.dateAuthorized',
            'storedTotalPrice' => 'commerce_orders.totalPrice',
            'storedTotalPaid' => 'commerce_orders.totalPaid',
            'storedItemTotal' => 'commerce_orders.itemTotal',
            'storedTotalDiscount' => 'commerce_orders.totalDiscount',
            'storedTotalShippingCost' => 'commerce_orders.totalShippingCost',
            'storedTotalTax' => 'commerce_orders.totalTax',
            'storedTotalTaxIncluded' => 'commerce_orders.totalTaxIncluded',
            'storedItemSubtotal' => 'commerce_orders.itemSubtotal',
            'storedTotalQty' => 'commerce_orders.totalQty',
            'commerce_orders.shippingMethodName',
            'commerce_orders.orderSiteId',
            'commerce_orders.orderLanguage',
            'commerce_orders.orderCompletedEmail',
        ]);

        // Addresses table joined for sorting purposes
        $this->query->leftJoin(CraftTable::ADDRESSES . ' billing_address', '[[billing_address.id]] = [[commerce_orders.billingAddressId]]');
        $this->subQuery->leftJoin(CraftTable::ADDRESSES . ' billing_address', '[[billing_address.id]] = [[commerce_orders.billingAddressId]]');
        $this->query->leftJoin(CraftTable::ADDRESSES . ' shipping_address', '[[shipping_address.id]] = [[commerce_orders.shippingAddressId]]');
        $this->subQuery->leftJoin(CraftTable::ADDRESSES . ' shipping_address', '[[shipping_address.id]] = [[commerce_orders.shippingAddressId]]');

        if (isset($this->number)) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->number) || $this->number === '') {
                return false;
            }
            $this->subQuery->andWhere(['commerce_orders.number' => $this->number]);
        }

        if (isset($this->shortNumber)) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->shortNumber) || $this->shortNumber === '') {
                return false;
            }

            $this->subQuery->andWhere(new Expression('LEFT([[commerce_orders.number]], 7) = :shortNumber', [':shortNumber' => $this->shortNumber]));
        }

        if (isset($this->storeId) && $this->storeId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.storeId', $this->storeId));
        }

        if (isset($this->origin) && $this->origin) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.origin', $this->origin));
        }

        if (isset($this->reference) && $this->reference) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.reference', $this->reference));
        }

        if (isset($this->couponCode)) {
            // Coupon code criteria is case-insensitive like in the adjuster
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.couponCode', $this->couponCode, caseInsensitive: true));
        }

        if (isset($this->email) && $this->email) {
            // Join and search the users table for email address
            $this->subQuery->leftJoin(CraftTable::USERS . ' users', '[[users.id]] = [[commerce_orders.customerId]]');
            $this->subQuery->andWhere(Db::parseParam('users.email', $this->email, '=', true));
        }
        
        if (isset($this->isCompleted)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('commerce_orders.isCompleted', $this->isCompleted, false));
        }

        if (isset($this->dateAuthorized)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateAuthorized', $this->datePaid));
        }

        if (isset($this->dateOrdered)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateOrdered', $this->dateOrdered));
        }

        if (isset($this->datePaid)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.datePaid', $this->datePaid));
        }

        if (isset($this->expiryDate)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.expiryDate', $this->expiryDate));
        }

        if (isset($this->orderStatusId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderStatusId', $this->orderStatusId));
        }

        if (isset($this->shippingMethodHandle)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.shippingMethodHandle', $this->shippingMethodHandle));
        }

        if (isset($this->orderLanguage)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderLanguage', $this->orderLanguage));
        }

        if (isset($this->orderSiteId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderSiteId', $this->orderSiteId));
        }

        if (isset($this->customerId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.customerId', $this->customerId));
        }

        if (isset($this->gatewayId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.gatewayId', $this->gatewayId));
        }

        if (isset($this->total)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.total', $this->total));
        }

        if (isset($this->totalPrice)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalPrice', $this->totalPrice));
        }

        if (isset($this->totalPaid)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalPaid', $this->totalPaid));
        }

        if (isset($this->itemTotal)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.itemTotal', $this->itemTotal));
        }

        if (isset($this->itemSubtotal)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.itemSubtotal', $this->itemSubtotal));
        }

        if (isset($this->totalQty)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalQty', $this->totalQty));
        }

        if (isset($this->totalWeight)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalWeight', $this->totalWeight));
        }

        if (isset($this->totalDiscount)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalDiscount', $this->totalDiscount));
        }

        if (isset($this->totalTax)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.totalTax', $this->totalTax));
        }

        // Allow true but not null
        if (isset($this->isPaid) && $this->isPaid) {
            $this->subQuery->andWhere(new Expression('[[commerce_orders.totalPaid]] >= [[commerce_orders.totalPrice]]'));
        }

        // Allow true but not null
        if (isset($this->isUnpaid) && $this->isUnpaid) {
            $this->subQuery->andWhere(new Expression('[[commerce_orders.totalPaid]] < [[commerce_orders.totalPrice]]'));
        }

        // Allow integer/PurchasableInterface object or array of integers/PurchasableInterface objects
        if (isset($this->hasPurchasables)) {
            $purchasableIds = [];

            if (!is_array($this->hasPurchasables)) {
                $this->hasPurchasables = [$this->hasPurchasables];
            }

            foreach ($this->hasPurchasables as $purchasable) {
                if ($purchasable instanceof PurchasableInterface) {
                    $purchasableIds[] = $purchasable->getId();
                } elseif (is_numeric($purchasable)) {
                    $purchasableIds[] = $purchasable;
                }
            }

            // Remove any blank purchasable IDs (if any)
            $purchasableIds = array_filter($purchasableIds);

            $this->subQuery->andWhere([
                'exists',
                (new Query())
                    ->from(['lineitems' => Table::LINEITEMS])
                    ->where(new Expression('[[lineitems.orderId]] = [[elements.id]]'))
                    ->andWhere(['[[lineitems.purchasableId]]' => $purchasableIds]),
            ]);
        }

        // Allow true or false but not null
        if (isset($this->hasTransactions)) {
            $this->subQuery->andWhere([
                $this->hasTransactions ? 'exists' : 'not exists',
                (new Query())
                    ->from(['transactions' => Table::TRANSACTIONS])
                    ->where(new Expression('[[transactions.orderId]] = [[elements.id]]')),
            ]);
        }

        // Allow true or false but not null
        if (isset($this->hasLineItems)) {
            $this->subQuery->andWhere([
                $this->hasLineItems ? 'exists' : 'not exists',
                (new Query())
                    ->from(['lineitems' => Table::LINEITEMS])
                    ->where(new Expression('[[lineitems.orderId]] = [[elements.id]]')),
            ]);
        }

        return parent::beforePrepare();
    }
}
