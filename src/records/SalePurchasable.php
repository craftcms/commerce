<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Sale product record.
 *
 * @property int $id
 * @property int $saleId
 * @property ActiveQueryInterface $sale
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property string $purchasableType
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SalePurchasable extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_sale_purchasables}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['saleId', 'purchasableId'], 'unique', 'targetAttribute' => ['saleId', 'purchasableId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getSale(): ActiveQueryInterface
    {
        return $this->hasOne(Sale::class, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['saleId' => 'id']);
    }
}
