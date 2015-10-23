<?php
namespace Craft;

/**
 * Tax rate record.
 *
 * @property int $id
 * @property string $name
 * @property float $rate
 * @property bool $include
 * @property bool $showInLabel
 * @property int $taxZoneId
 * @property int $taxCategoryId
 *
 * @property Commerce_TaxZoneRecord $taxZone
 * @property Commerce_TaxCategoryRecord $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_TaxRateRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_taxrates';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['taxZoneId']],
            ['columns' => ['taxCategoryId']],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'taxZone' => [
                static::BELONGS_TO,
                'Commerce_TaxZoneRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'taxCategory' => [
                static::BELONGS_TO,
                'Commerce_TaxCategoryRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'rate' => [
                AttributeType::Number,
                'required' => true,
                'decimals' => 5
            ],
            'include' => [
                AttributeType::Bool,
                'default' => 0,
                'required' => true
            ],
            'showInLabel' => [
                AttributeType::Bool,
                'default' => 0,
                'required' => true
            ],
        ];
    }
}