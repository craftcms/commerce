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
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Product record.
 *
 * @property float $defaultHeight
 * @property float $defaultLength
 * @property float $defaultPrice
 * @property string $defaultSku
 * @property int $defaultVariantId
 * @property float $defaultWeight
 * @property float $defaultWidth
 * @property ActiveQueryInterface $element
 * @property DateTime $expiryDate
 * @property int $id
 * @property DateTime $postDate
 * @property ActiveQueryInterface $type
 * @property int $typeId
 * @property Variant[] $variants
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PRODUCTS;
    }

    public function getVariants(): ActiveQueryInterface
    {
        return $this->hasMany(Variant::class, ['productId' => 'id']);
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id' => 'productTypeId']);
    }
}
