<?php
namespace Craft;

/**
 * Sale user group record.
 *
 * @property int $id
 * @property int $saleId
 * @property int $userGroupId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_SaleUserGroupRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_sale_usergroups';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['saleId', 'userGroupId'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'sale' => [
                static::BELONGS_TO,
                'Commerce_SaleRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'userGroup' => [
                static::BELONGS_TO,
                'UserGroupRecord',
                'onDelete' => self::CASCADE,
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
            'saleId' => [AttributeType::Number, 'required' => true],
            'userGroupId' => [AttributeType::Number, 'required' => true],
        ];
    }

}