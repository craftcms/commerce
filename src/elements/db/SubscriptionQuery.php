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
use craft\helpers\Db;
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
 * @doc-path subscriptions.md
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
     * @var mixed The user id of the subscriber
     */
    public mixed $userId = null;

    /**
     * @var mixed The subscription plan id
     */
    public mixed $planId = null;

    /**
     * @var mixed The gateway id
     */
    public mixed $gatewayId = null;

    /**
     * @var mixed The id of the order that the license must be a part of.
     */
    public mixed $orderId = null;

    /**
     * @var mixed The gateway reference for subscription
     */
    public mixed $reference = null;

    /**
     * @var mixed Number of trial days for the subscription
     */
    public mixed $trialDays = null;

    /**
     * @var bool|null Whether the subscription is currently on trial.
     */
    public ?bool $onTrial = null;

    /**
     * @var mixed Time of next payment for the subscription
     */
    public mixed $nextPaymentDate = null;

    /**
     * @var bool|null Whether the subscription is canceled
     */
    public ?bool $isCanceled = null;

    /**
     * @var bool|null Whether the subscription is suspended
     */
    public ?bool $isSuspended = null;

    /**
     * @var mixed The date the subscription ceased to be active
     */
    public mixed $dateSuspended = null;

    /**
     * @var bool|null Whether the subscription has started
     */
    public ?bool $hasStarted = null;

    /**
     * @var mixed The time the subscription was canceled
     */
    public mixed $dateCanceled = null;

    /**
     * @var bool|null Whether the subscription has expired
     */
    public ?bool $isExpired = null;

    /**
     * @var mixed The date the subscription ceased to be active
     */
    public mixed $dateExpired = null;

    /**
     * @var array
     */
    protected array $defaultOrderBy = ['commerce_subscriptions.dateCreated' => SORT_DESC];

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
     *   .user(currentUser)
     *   .all() %}
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
    public function user(mixed $value): SubscriptionQuery
    {
        if ($value instanceof User) {
            $this->userId = $value->id;
        } elseif ($value !== null) {
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
     *   .plan('supporter')
     *   .all() %}
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
    public function plan(mixed $value): SubscriptionQuery
    {
        if ($value instanceof Plan) {
            $this->planId = $value->id;
        } elseif ($value !== null) {
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
     *   .userId(currentUser.id)
     *   .all() %}
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
    public function userId(mixed $value): SubscriptionQuery
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
    public function planId(mixed $value): SubscriptionQuery
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
     * @param mixed $value The property value
     * @return static self reference
     */
    public function gatewayId(mixed $value): SubscriptionQuery
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
     * @param mixed $value The property value
     * @return static self reference
     */
    public function orderId(mixed $value): SubscriptionQuery
    {
        $this->orderId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the reference.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function reference(mixed $value): SubscriptionQuery
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
    public function trialDays(mixed $value): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .onTrial()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch trialed subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isPaid()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function onTrial(?bool $value = true): SubscriptionQuery
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
     *   .nextPaymentDate("< #{aWeekFromNow}")
     *   .all() %}
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
    public function nextPaymentDate(mixed $value): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isCanceled()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch canceled subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isCanceled()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isCanceled(?bool $value = true): SubscriptionQuery
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
     *   .dateCanceled(">= #{aWeekAgo}")
     *   .all() %}
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
    public function dateCanceled(mixed $value): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .hasStarted()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch started subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->hasStarted()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function hasStarted(?bool $value = true): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isSuspended()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch suspended subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isSuspended()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isSuspended(?bool $value = true): SubscriptionQuery
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
     *   .dateSuspended(">= #{aWeekAgo}")
     *   .all() %}
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
    public function dateSuspended(mixed $value): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .isExpired()
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch expired subscriptions
     * ${elements-var} = {element-class}::find()
     *     ->isExpired()
     *     ->all();
     * ```
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function isExpired(?bool $value = true): SubscriptionQuery
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
     *   .dateExpired(">= #{aWeekAgo}")
     *   .all() %}
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
    public function dateExpired(mixed $value): SubscriptionQuery
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
     * {% set {elements-var} = {twig-method}
     *   .status('expired')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch expired {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('expired')
     *     ->all();
     * ```
     */
    public function status(array|string|null $value): SubscriptionQuery
    {
        parent::status($value);
        if ($value === null) {
            unset($this->isSuspended, $this->hasStarted);
        }

        return $this;
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
            'commerce_subscriptions.dateCanceled',
            'commerce_subscriptions.dateExpired',
            'commerce_subscriptions.dateSuspended',
            'commerce_subscriptions.gatewayId',
            'commerce_subscriptions.hasStarted',
            'commerce_subscriptions.id',
            'commerce_subscriptions.isCanceled',
            'commerce_subscriptions.isExpired',
            'commerce_subscriptions.isSuspended',
            'commerce_subscriptions.nextPaymentDate',
            'commerce_subscriptions.orderId',
            'commerce_subscriptions.planId',
            'commerce_subscriptions.reference',
            'commerce_subscriptions.subscriptionData',
            'commerce_subscriptions.trialDays',
            'commerce_subscriptions.userId',
            'commerce_subscriptions.returnUrl',
        ]);

        if (isset($this->userId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.userId', $this->userId));
        }

        if (isset($this->planId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.planId', $this->planId));
        }

        if (isset($this->gatewayId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.gatewayId', $this->gatewayId));
        }

        if (isset($this->orderId)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.orderId', $this->orderId));
        }

        if (isset($this->reference)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.reference', $this->reference));
        }

        if (isset($this->trialDays)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_subscriptions.trialDays', $this->trialDays));
        }

        if (isset($this->nextPaymentDate)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.nextPaymentDate', $this->nextPaymentDate));
        }

        if (isset($this->isCanceled)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('commerce_subscriptions.isCanceled', $this->isCanceled, false));
        }

        if (isset($this->dateCanceled)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateCanceled', $this->dateCanceled));
        }

        if (isset($this->hasStarted)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('commerce_subscriptions.hasStarted', $this->hasStarted, false));
        }

        if (isset($this->isSuspended)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('commerce_subscriptions.isSuspended', $this->isSuspended, false));
        }

        if (isset($this->dateSuspended)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateSuspended', $this->dateSuspended));
        }

        if (isset($this->isExpired)) {
            $this->subQuery->andWhere(Db::parseBooleanParam('commerce_subscriptions.isExpired', $this->isExpired, false));
        }

        if (isset($this->dateExpired)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_subscriptions.dateExpired', $this->dateExpired));
        }

        if (isset($this->onTrial) && $this->onTrial === true) {
            $this->subQuery->andWhere($this->_getTrialCondition(true));
        } elseif (isset($this->onTrial) && $this->onTrial === false) {
            $this->subQuery->andWhere($this->_getTrialCondition(false));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            Subscription::STATUS_ACTIVE => [
                'commerce_subscriptions.isExpired' => '0',
            ],
            Subscription::STATUS_EXPIRED => [
                'commerce_subscriptions.isExpired' => '1',
            ],
            default => parent::statusCondition($status),
        };
    }

    /**
     * @inheritdoc
     * @deprecated in 4.0.0. `status(null)` should be used instead.
     */
    public function anyStatus(): SubscriptionQuery
    {
        parent::status(null);
        unset($this->isSuspended, $this->hasStarted);

        return $this;
    }

    /**
     * Returns the SQL condition to use for trial status.
     *
     * @param bool $onTrial
     * @return mixed
     */
    private function _getTrialCondition(bool $onTrial): mixed
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
