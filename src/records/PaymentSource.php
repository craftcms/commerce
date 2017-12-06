<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * Payment source record.
 *
 * @property int               $id
 * @property int               $userId
 * @property int               $gatewayId
 * @property string            $token
 * @property string            $description
 * @property string            $response
 * @property Gateway           $gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class PaymentSource extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_paymentsources}}';
    }

    /**
     * Return the payment source's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['gatewayId' => 'id']);
    }


    /**
     * @return ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'customerId']);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {

        return [
            [['token'], 'unique', 'targetAttribute' => ['gatewayId', 'token']],
            [['gatewayId', 'userId', 'token', 'description'], 'required']
        ];

    }
}
