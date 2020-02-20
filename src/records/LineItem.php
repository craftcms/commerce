<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Line Item record.
 *
 * @property float $height
 * @property int $id
 * @property float $length
 * @property string $note
 * @property string $privateNote
 * @property mixed $options
 * @property string $description
 * @property string $optionsSignature
 * @property Order $order
 * @property int $orderId
 * @property int|null lineItemStatusId
 * @property float $price
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property int $qty
 * @property float $saleAmount
 * @property float $salePrice
 * @property string $sku
 * @property ActiveQueryInterface $shippingCategory
 * @property int $shippingCategoryId
 * @property string $snapshot
 * @property TaxCategory $taxCategory
 * @property int $taxCategoryId
 * @property float $total
 * @property float $subtotal
 * @property float $weight
 * @property float $width
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItem extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::LINEITEMS;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'purchasableId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getLineItemStatus(): ActiveQueryInterface
    {
        return $this->hasOne(LineItemStatus::class, ['id' => 'lineItemStatusId']);
    }
}
