<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax rate record.
 *
 * @property int $id
 * @property bool $include
 * @property bool $isVat
 * @property string $name
 * @property float $rate
 * @property string $taxable
 * @property TaxCategory $taxCategory
 * @property int $taxCategoryId
 * @property TaxZone $taxZone
 * @property int $taxZoneId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRate extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TAXABLE_PRICE = 'price';
    const TAXABLE_SHIPPING = 'shipping';
    const TAXABLE_PRICE_SHIPPING = 'price_shipping';
    const TAXABLE_ORDER_TOTAL_SHIPPING = 'order_total_shipping';
    const TAXABLE_ORDER_TOTAL_PRICE = 'order_total_price';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_taxrates}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxZone(): ActiveQueryInterface
    {
        return $this->hasOne(TaxZone::class, ['id' => 'taxZoneId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }
}
