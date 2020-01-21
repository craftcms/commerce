<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\db\Table;
use craft\commerce\elements\Subscription;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;
use yii\db\Expression;

/**
 * SubscriptionQuery represents a SELECT SQL statement for subscriptions in a way that is independent of DBMS.
 *
 * @method Subscription[]|array all($db = null)
 * @method Subscription|array|false one($db = null)
 * @method Subscription|array|false nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @replace {element} subscription
 * @replace {elements} subscriptions
 * @replace {twig-method} craft.subscriptions()
 * @replace {myElement} mySubscription
 * @replace {element-class} \craft\commerce\elements\Subscription
 * @supports-status-param
 */
class SubscriptionQuery extends ElementQuery
{
    /**
     * @var int|int[] The user id of the subscriber
     */
    public $userId;

    /**
     * @var int|int[] The subscription plan id
     */
    public $planId;

    /**
     * @var int|int[] The gateway id
     */
    public $gatewayId;

    /**
     * @var int|int[] The id of the order that the license must be a part of.
     */
    public $orderId;

    /**
     * @var string|string[] The gateway reference for subscription
     */
    public $reference;

    /**
     * @var int|int[] Number of trial days for the subscription
     */
    public $trialDays;

    /**
     * @var bool Whether the subscription is currently on trial.
     */
    public $onTrial;

    /**
     * @var DateTime Time of next payment for the subscription
     */
    public $nextPaymentDate;

    /**
     * @var bool Whether the subscription is canceled
     */
    public $isCanceled;

    /**
     * @var bool Whether the subscription is suspended
     */
    public $isSuspended;

    /**
     * @var DateTime The date the subscription ceased to be active
     */
    public $dateSuspended;

    /**
     * @var bool Whether the subscription has started
     */
    public $hasStarted;

    /**
     * @var DateTime The time the subscription was canceled
     */
    public $dateCanceled;

    /**
     * @var bool Whether the subscription has expired
     */
    public $isExpired;

    /**
     * @var DateTime The date the subscription ceased to be active
     */
    public $dateExpired;

    /**
     * @var array
     */
    protected $defaultOrderBy = ['commerce_subscriptions.dateCreated' => SORT_DESC];


    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Subscription::STATUS_ACTIVE;
            $config['hasStarted'] = true;
            $config['isSuspended'] = false;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'user':
                $this->user($value);
                break;
            case 'plan':
                $this->plan($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | for a user account with a username of `foo`
     * | `['foo', 'bar']` | for user accounts with a username of `foo` or `bar`.
     * | a [[User|User]] object | for a user account represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's subscriptions #}
     * {% set {elements-var} = {twig-method}
     *     .user(currentUser)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's subscriptions
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->user($user)
     *     ->all();
     * ```
     *
     * @param mixed $value
     * @return static self reference
     */
    public function user($value)
    {
        if ($value instanceof User) {
            $this->userId = $value->id;
        } else if ($value !== null) {
            $this->userId = (new Query())
                ->select(['id'])
                ->from(['{{%users}}'])
                ->where(Db::parseParam('username', $value))
                ->column();
        } else {
            $this->userId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscription plan.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | for a plan with a handle of `foo`.
     * | `['foo', 'bar']` | for plans with a handle of `foo` or `bar`.
     * | a [[Plan|Plan]] object | for a plan represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch Supporter plan subscriptions #}
     * {% set {elements-var} = {twig-method}
     *     .plan('supporter')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch Supporter plan subscriptions
     * ${elements-var} = {php-method}
     *     ->plan('supporter')
     *     ->all();
     * ```
     *
     * @param mixed $value
     * @return static self reference
     */
    public function plan($value)
    {
        if ($value instanceof Plan) {
            $this->planId = $value->id;
        } else if ($value !== null) {
            $this->planId = (new Query())
                ->select(['id'])
                ->from([Table::PLANS])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->planId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ user accounts’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a user account with an ID of 1.
     * | `[1, 2]` | for user accounts with an ID of 1 or 2.
     * | `['not', 1, 2]` | for user accounts not with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch the current user's subscriptions #}
     * {% set {elements-var} = {twig-method}
     *     .userId(currentUser.id)
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch the current user's subscriptions
     * $user = Craft::$app->user->getIdentity();
     * ${elements-var} = {php-method}
     *     ->userId($user->id)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function userId($value)
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscription plans’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | for a plan with an ID of 1.
     * | `[1, 2]` | for plans with an ID of 1 or 2.
     * | `['not', 1, 2]` | for plans not with an ID of 1 or 2.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function planId($value)
    {
        $this->planId = $value;
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
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function gatewayId($value)
    {
        $this->gatewayId = $value;
        return $this;
    }


    /**
     * Narrows the query results based on the order, per its ID.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | with an order with an ID of 1.
     * | `'not 1'` | not with an order with an ID of 1.
     * | `[1, 2]` | with an order with an ID of 1 or 2.
     * | `['not', 1, 2]` | not with an order with an ID of 1 or 2.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function orderId($value)
    {
        $this->orderId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the reference.
     *
     * @param string|string[] $value The property value
     * @return static self reference
     */
    public function reference($value)
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the number of trial days.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function trialDays($value)
    {
        $this->trialDays = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are on trial.
     *
     * ---
     *
     * ```twig
     * {# Fetch trialed subscriptions #}
     * {% set {elements-var} = {twig-function}
     *     .onTrial()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch trialed subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isPaid()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function onTrial(bool $value = true)
    {
        $this->onTrial = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ next payment dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | with a next payment on or after 2018-04-01.
     * | `'< 2018-05-01'` | with a next payment before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | with a next payment between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a payment due soon #}
     * {% set aWeekFromNow = date('+7 days')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .nextPaymentDate("< #{aWeekFromNow}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a payment due soon
     * $aWeekFromNow = new \DateTime('+7 days')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->nextPaymentDate("< {$aWeekFromNow}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function nextPaymentDate($value)
    {
        $this->nextPaymentDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are canceled.
     *
     * ---
     *
     * ```twig
     * {# Fetch canceled subscriptions #}
     * {% set {elements-var} = {twig-function}
     *     .isCanceled()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch canceled subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isCanceled()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isCanceled(bool $value = true)
    {
        $this->isCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ cancellation date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were canceled on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were canceled before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were canceled between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were canceled recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .dateCanceled(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were canceled recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateCanceled(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateCanceled($value)
    {
        $this->dateCanceled = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that have started.
     *
     * ---
     *
     * ```twig
     * {# Fetch started subscriptions #}
     * {% set {elements-var} = {twig-function}
     *     .hasStarted()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch started subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->hasStarted()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function hasStarted(bool $value = true)
    {
        $this->hasStarted = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that are suspended.
     *
     * ---
     *
     * ```twig
     * {# Fetch suspended subscriptions #}
     * {% set {elements-var} = {twig-function}
     *     .isSuspended()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch suspended subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isSuspended()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isSuspended(bool $value = true)
    {
        $this->isSuspended = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ suspension date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were suspended on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were suspended before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were suspended between 2018-04-01 and 2018-05-01.
     * ---
     *
     * ```twig
     * {# Fetch {elements} that were suspended recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .dateSuspended(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that were suspended recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateSuspended(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateSuspended($value)
    {
        $this->dateSuspended = $value;
        return $this;
    }

    /**
     * Narrows the query results to only subscriptions that have expired.
     *
     * ---
     *
     * ```twig
     * {# Fetch expired subscriptions #}
     * {% set {elements-var} = {twig-function}
     *     .isExpired()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch expired subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isExpired()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isExpired(bool $value = true)
    {
        $this->isExpired = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the subscriptions’ expiration date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that expired on or after 2018-04-01.
     * | `'< 2018-05-01'` | that expired before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that expired between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} that expired recently #}
     * {% set aWeekAgo = date('7 days ago')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *     .dateExpired(">= #{aWeekAgo}")
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} that expired recently
     * $aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->dateExpired(">= {$aWeekAgo}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateExpired($value)
    {
        $this->dateExpired = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'active'` _(default)_ | that are active.
     * | `'expired'` | that have expired.
     *
     * ---
     *
     * ```twig
     * {# Fetch expired {elements} #}
     * {% set {elements-var} = {twig-function}
     *     .status('expired')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch expired {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('expired')
     *     ->all();
     * ```
     */
    public function status($value)
    {
        return parent::status($value);
    }


    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'plan' were set to invalid handles
        if ($this->planId === []) {
            return false;
        }

        $this->joinElementTable('commerce_subscriptions');
        $this->subQuery->innerJoin('{{%users}} users', '[[commerce_subscriptions.userId]] = [[users.id]]');

        $this->query->select([
            'commerce_subscriptions.id',
            'commerce_subscriptions.userId',
            'commerce_subscriptions.planId',
            'commerce_subscriptions.gatewayId',
            'commerce_subscriptions.orderId',
            'commerce_subscriptions.reference',
            'commerce_subscriptions.subscriptionData',
            'commerce_subscriptions.trialDays',
            'commerce_subscriptions.nextPaymentDate',
            'commerce_subscriptions.isCanceled',
            'commerce_subscriptions.dateCanceled',
            'commerce_subscriptions.isExpired',
            'commerce_subscriptions.dateExpired',
            'commerce_subscriptions.hasStarted',
            'commerce_subscriptions.isSuspended',
            'commerce_subscriptions.dateSuspended',
        ]);

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.userId', $this->userId));
        }

        if ($this->planId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.planId', $this->planId));
        }

        if ($this->gatewayId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.gatewayId', $this->gatewayId));
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.orderId', $this->orderId));
        }

        if ($this->reference) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.reference', $this->reference));
        }

        if ($this->trialDays) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.trialDays', $this->trialDays));
        }

        if ($this->nextPaymentDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.nextPaymentDate', $this->nextPaymentDate));
        }

        if ($this->isCanceled) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.isCanceled', $this->isCanceled));
        }

        if ($this->dateCanceled) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateCanceled', $this->dateCanceled));
        }

        if ($this->hasStarted !== null) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.hasStarted', $this->hasStarted));
        }

        if ($this->isSuspended !== null) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.isSuspended', $this->isSuspended));
        }

        if ($this->dateSuspended) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateSuspended', $this->dateSuspended));
        }

        if ($this->isExpired) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.isExpired', $this->isExpired));
        }

        if ($this->dateExpired) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateExpired', $this->dateExpired));
        }

        if ($this->onTrial === true) {
            $this->subQuery->andWhere($this->_getTrialCondition(true));
        } else if ($this->onTrial === false) {
            $this->subQuery->andWhere($this->_getTrialCondition(false));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        switch ($status) {
            case Subscription::STATUS_ACTIVE:
                return [
                    'commerce_subscriptions.isExpired' => '0',
                ];
            case Subscription::STATUS_EXPIRED:
                return [
                    'commerce_subscriptions.isExpired' => '1',
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    /**
     * @inheritdoc
     */

    public function anyStatus()
    {
        $this->isSuspended = null;
        $this->hasStarted = null;
        return parent::anyStatus();
    }

    /**
     * Returns the SQL condition to use for trial status.
     *
     * @param bool $onTrial
     * @return mixed
     */
    private function _getTrialCondition(bool $onTrial)
    {
        if ($onTrial) {
            if (Craft::$app->getDb()->getIsPgsql()) {
                return new Expression("NOW() <= [[commerce_subscriptions.dateCreated]] + [[commerce_subscriptions.trialDays]] * INTERVAL '1 day'");
            }

            return new Expression('NOW() <= ADDDATE([[commerce_subscriptions.dateCreated]], [[commerce_subscriptions.trialDays]])');
        }

        if (Craft::$app->getDb()->getIsPgsql()) {
            return new Expression("NOW() > [[commerce_subscriptions.dateCreated]] + [[commerce_subscriptions.trialDays]] * INTERVAL '1 day'");
        }

        return new Expression('NOW() > ADDDATE([[commerce_subscriptions.dateCreated]], [[commerce_subscriptions.trialDays]])');
    }
}
