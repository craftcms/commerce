<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\User;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Subscription record.
 *
 * @property DateTime $dateCanceled
 * @property DateTime $dateExpired
 * @property DateTime $dateSuspended
 * @property ActiveQueryInterface $gateway
 * @property int $gatewayId
 * @property int $id
 * @property bool $isCanceled
 * @property bool $isExpired
 * @property bool $isSuspended
 * @property bool $hasStarted
 * @property DateTime $nextPaymentDate
 * @property int $orderId
 * @property ActiveQueryInterface $plan
 * @property int $planId
 * @property string $reference
 * @property string $subscriptionData
 * @property int $trialDays
 * @property ActiveQueryInterface $user
 * @property int $userId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Subscription extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SUBSCRIPTIONS;
    }

    /**
     * Return the subscription's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['gatewayId' => 'id']);
    }

    /**
     * Return the subscription's user
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['userId' => 'id']);
    }

    /**
     * Return the subscription's plan
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getPlan(): ActiveQueryInterface
    {
        return $this->hasOne(Plan::class, ['planId' => 'id']);
    }
}
