<?php
namespace Craft;

/**
 * Discount user record.
 *
 * @property int $id
 * @property int $discountId
 * @property int $userGroupId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_DiscountUserGroupRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_discount_usergroups';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['discountId', 'userGroupId'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'discount' => [
                static::BELONGS_TO,
                'Commerce_DiscountRecord',
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
            'discountId' => [AttributeType::Number, 'required' => true],
            'userGroupId' => [AttributeType::Number, 'required' => true],
        ];
    }

}