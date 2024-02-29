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
 * Variant record.
 *
 * @property ActiveQueryInterface $element
 * @property int $id
 * @property bool $isDefault
 * @property int $maxQty
 * @property int $minQty
 * @property Product $product
 * @property int $primaryOwnerId
 * @property int $sku
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variant extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::VARIANTS;
    }

    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id', 'productId']);
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id', 'id']);
    }
}
