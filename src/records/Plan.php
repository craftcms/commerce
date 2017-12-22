<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property int                  $id
 * @property int                  $gatewayId
 * @property string               $name
 * @property string               $handle
 * @property string               $reference
 * @property string               $billingPeriod
 * @property int                  $billingPeriodCount
 * @property float                $paymentAmount
 * @property float                $setupCost
 * @property string               $currency
 * @property string               $response
 * @property ActiveQueryInterface $gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Plan extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_plans}}';
    }

    /**
     * Return the subscription plan's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['gatewayId' => 'id']);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['reference'], 'unique', 'targetAttribute' => ['gatewayId', 'reference']],
            [['gatewayId', 'reference', 'name', 'handle', 'billingPeriod', 'billingPeriodCount', 'paymentAmount', 'currency'], 'required']
        ];
    }

}
