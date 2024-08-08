<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\base\StoreRecordTrait;
use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * PurchasableStore record.
 *
 * @property int $id
 * @property int $purchasableId
 * @property int $storeId
 * @property float|null $basePrice
 * @property float|null $basePromotionalPrice
 * @property int|null $stock
 * @property bool $inventoryTracked
 * @property int|null $minQty
 * @property int|null $maxQty
 * @property bool $promotable
 * @property bool $availableForPurchase
 * @property bool $freeShipping
 * @property int $shippingCategoryId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableStore extends ActiveRecord
{
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PURCHASABLES_STORES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['id' => 'purchasableId']);
    }
}
