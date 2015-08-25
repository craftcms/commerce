<?php

namespace Craft;

/**
 * Class Market_ProductRecord
 *
 * @property int                      id
 * @property int                      taxCategoryId
 * @property int                      typeId
 * @property int                      authorId
 * @property DateTime                 availableOn
 * @property DateTime                 expiresOn
 * @property bool                     promotable
 * @property bool                     freeShipping
 *
 * @property Market_VariantRecord     $master
 * @property Market_VariantRecord[]   variants
 * @property Market_TaxCategoryRecord taxCategory
 * @package Craft
 */
class Market_ProductRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'market_products';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'element'     => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
            'type'        => [
                static::BELONGS_TO,
                'Market_ProductTypeRecord',
                'onDelete' => static::CASCADE
            ],
            'author'      => [
                static::BELONGS_TO,
                'UserRecord',
                'onDelete' => static::CASCADE
            ],
            'master'      => [
                static::HAS_ONE,
                'Market_VariantRecord',
                'productId',
                'condition' => 'master.isMaster = 1'
            ],
            'variants' => [
                static::HAS_MANY,
                'Market_VariantRecord',
                'productId'
            ],
            'taxCategory' => [
                static::BELONGS_TO,
                'Market_TaxCategoryRecord',
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
            ['columns' => ['availableOn']],
            ['columns' => ['expiresOn']],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'availableOn'   => AttributeType::DateTime,
            'expiresOn'     => AttributeType::DateTime,
            'promotable'    => AttributeType::Bool,
            'freeShipping'  => AttributeType::Bool
        ];
    }

}