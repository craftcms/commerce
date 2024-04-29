<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax Rate record.
 *
 * @property int $id
 * @property bool $include
 * @property bool $removeIncluded
 * @property bool $removeVatIncluded
 * @property bool $isVat
 * @property string $name
 * @property string $code
 * @property float $rate
 * @property string $taxable
 * @property TaxCategory $taxCategory
 * @property int $taxCategoryId
 * @property TaxZone $taxZone
 * @property bool $isEverywhere
 * @property int $taxZoneId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRate extends ActiveRecord
{
    /**
     * @var string Tax subject is line item price.
     */
    public const TAXABLE_PURCHASABLE = 'purchasable';

    /**
     * @var string Tax subject is line item price.
     */
    public const TAXABLE_PRICE = 'price';

    /**
     * @var string Tax subject is line item shipping cost.
     */
    public const TAXABLE_SHIPPING = 'shipping';

    /**
     * @var string Tax subject is line item price and shipping cost.
     */
    public const TAXABLE_PRICE_SHIPPING = 'price_shipping';

    /**
     * @var string Tax subject is order total shipping cost.
     */
    public const TAXABLE_ORDER_TOTAL_SHIPPING = 'order_total_shipping';

    /**
     * @var string Tax subject is order total price.
     */
    public const TAXABLE_ORDER_TOTAL_PRICE = 'order_total_price';

    /**
     * @var array Order-specific tax subject options.
     */
    public const ORDER_TAXABALES = [
        self::TAXABLE_ORDER_TOTAL_PRICE,
        self::TAXABLE_ORDER_TOTAL_SHIPPING,
    ];

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TAXRATES;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getTaxZone(): ActiveQueryInterface
    {
        return $this->hasOne(TaxZone::class, ['id' => 'taxZoneId']);
    }

    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }
}
