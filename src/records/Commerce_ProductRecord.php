<?php
namespace Craft;

/**
 * Product record.
 *
 * @property int $id
 * @property int $taxCategoryId
 * @property int $typeId
 * @property int $authorId
 * @property DateTime $postDate
 * @property DateTime $expiryDate
 * @property bool $promotable
 * @property bool $freeShipping
 *
 * @property Commerce_VariantRecord $implicit
 * @property Commerce_VariantRecord[] $variants
 * @property Commerce_TaxCategoryRecord $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ProductRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_products';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'element' => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
            'type' => [
                static::BELONGS_TO,
                'Commerce_ProductTypeRecord',
                'onDelete' => static::CASCADE
            ],
            'author' => [
                static::BELONGS_TO,
                'UserRecord',
                'onDelete' => static::CASCADE
            ],
            'variants' => [
                static::HAS_MANY,
                'Commerce_VariantRecord',
                'productId'
            ],
            'taxCategory' => [
                static::BELONGS_TO,
                'Commerce_TaxCategoryRecord',
                'required' => true
            ],
        ];
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['typeId']],
            ['columns' => ['postDate']],
            ['columns' => ['expiryDate']],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'postDate' => AttributeType::DateTime,
            'expiryDate' => AttributeType::DateTime,
            'promotable' => AttributeType::Bool,
            'freeShipping' => AttributeType::Bool
        ];
    }

}
