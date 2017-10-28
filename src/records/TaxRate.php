<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax rate record.
 *
 * @property int         $id
 * @property string      $name
 * @property float       $rate
 * @property bool        $include
 * @property bool        $isVat
 * @property string      $taxable
 * @property int         $taxZoneId
 * @property int         $taxCategoryId
 * @property TaxZone     $taxZone
 * @property TaxCategory $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
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
     * @return string
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
