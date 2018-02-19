<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Variant record.
 *
 * @property ActiveQueryInterface $element
 * @property float $height
 * @property int $id
 * @property bool $isDefault
 * @property float $length
 * @property int $maxQty
 * @property int $minQty
 * @property float $price
 * @property Product $product
 * @property int $productId
 * @property string $sku
 * @property int $sortOrder
 * @property int $stock
 * @property bool $unlimitedStock
 * @property float $weight
 * @property float $width
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variant extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_variants}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['sku'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id', 'productId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id', 'id']);
    }
}
