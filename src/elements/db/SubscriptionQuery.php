<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\base\Element;
use craft\commerce\base\Plan;
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
 * LicenseQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 * @method Subscription[]|array all($db = null)
 * @method Subscription|array|false one($db = null)
 * @method Subscription|array|false nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionQuery extends ElementQuery
{
    // Properties
    // =========================================================================

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
     * @var DateTime The time the subscription was canceled
     */
    public $dateCanceled;

    /**
     * @var bool Whether the subscription has expired
     */
    public $isExpired;

    /**
     * @var DateTime The
     */
    public $dateExpired;

    /**
     * @var array
     */
    protected $defaultOrderBy = ['commerce_subscriptions.dateCreated' => SORT_DESC];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Element::STATUS_ENABLED;
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
            case 'subscribedBefore':
                $this->subscribedBefore($value);
                break;
            case 'subscribedAfter':
                $this->subscribedAfter($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[userId]] property based on a user element or username
     *
     * @param User|string $value
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
            $this->ownerId = null;
        }

        return $this;
    }

    /**
     * Sets the [[planId]] property based on a plan model or handle
     *
     * @param Product $value
     * @return static self reference
     */
    public function plan($value)
    {
        if ($value instanceof Plan) {
            $this->planId = $value->id;
        } else if ($value !== null) {
            $this->planId = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_plans}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->planId = null;
        }

        return $this;
    }

    /**
     * Sets the [[dateCreated]] property to only allow subscriptions before a given date
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function subscribedBefore($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '<' . $value;

        return $this;
    }

    /**
     * Sets the [[dateCreated]] property to only allow subscriptions after a given date
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function subscribedAfter($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '>=' . $value;

        return $this;
    }

    /**
     * Sets the [[userId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function userId($value)
    {
        $this->userId = $value;

        return $this;
    }

    /**
     * Sets the [[planId]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function planId($value)
    {
        $this->planId = $value;

        return $this;
    }

    /**
     * Sets the [[gatewayId]] property.
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
     * Sets the [[orderId]] property.
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
     * Sets the [[reference]] property.
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
     * Sets the [[trialDays]] property.
     *
     * @param int|int[] $value The property value
     * @return static self reference
     */
    public function trialDays($value)
    {
        $this->trialDays = $value;

        return $this;
    }

    /**
     * Sets the [[onTrial]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function onTrial($value)
    {
        $this->onTrial = $value;

        return $this;
    }

    /**
     * Sets the [[nextPaymentDate]] property.
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
     * Sets the [[isCanceled]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isCanceled(bool $value)
    {
        $this->isCanceled = $value;

        return $this;
    }

    /**
     * Sets the [[dateCanceled]] property.
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
     * Sets the [[isExpired]] property.
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function isExpired(bool $value)
    {
        $this->isExpired = $value;

        return $this;
    }

    /**
     * Sets the [[dateExpired]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function dateExpired($value)
    {
        $this->dateExpired = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

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
            'commerce_subscriptions.dateExpired'
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
                    'commerce_subscriptions.isCanceled' => '0',
                    'commerce_subscriptions.isExpired' => '1',
                ];
            case Subscription::STATUS_CANCELED:
                return [
                    'commerce_subscriptions.isCanceled' => '1',
                    'commerce_subscriptions.isExpired' => '1',
                ];
            case Subscription::STATUS_TRIAL:
                return $this->_getTrialCondition(true);
            default:
                return parent::statusCondition($status);
        }
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
