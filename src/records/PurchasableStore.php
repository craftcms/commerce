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
 * PurchasableStore record.
 *
 * @property int $id
 * @property int $purchasableId
 * @property int $storeId
 * @property float $price
 * @property float $promotionalPrice
 * @property int $stock
 * @property bool $hasUnlimitedStock
 * @property int $minQty
 * @property int $maxQty
 * @property bool $promotable
 * @property bool $availableForPurchase
 * @property bool $freeShipping
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableStore extends ActiveRecord
{
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

    /**
     * @return ActiveQueryInterface
     */
    public function getStore(): ActiveQueryInterface
    {
        return $this->hasOne(Store::class, ['id' => 'storeId']);
    }
}
