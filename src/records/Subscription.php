<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property int                  $id
 * @property int                  $userId
 * @property int                  $planId
 * @property int                  $gatewayId
 * @property string               $reference
 * @property int                  $trialDays
 * @property \DateTime            $nextPaymentDate
 * @property float                $paymentAmount
 * @property \DateTime            $expiryDate
 * @property string               $response
 * @property ActiveQueryInterface $gateway
 * @property ActiveQueryInterface $plan
 * @property ActiveQueryInterface $user
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
        return '{{%commerce_subscriptions}}';
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
     * Return the subscription's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['userId' => 'id']);
    }

    /**
     * Return the subscription's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getPlan(): ActiveQueryInterface
    {
        return $this->hasOne(Plan::class, ['planId' => 'id']);
    }
}
