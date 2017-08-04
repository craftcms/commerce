<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Sale product type record.
 *
 * @property int                          $id
 * @property int                          $saleId
 * @property \yii\db\ActiveQueryInterface $productType
 * @property \yii\db\ActiveQueryInterface $sale
 * @property int                          $productTypeId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class SaleProductType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_sale_producttypes}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['saleId', 'productTypeId'], 'unique', 'targetAttribute' => ['saleId', 'productTypeId']]
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
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['saleId' => 'id']);
    }
}