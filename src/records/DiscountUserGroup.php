<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Discount user record.
 *
 * @property int                          $id
 * @property int                          $discountId
 * @property \yii\db\ActiveQueryInterface $productType
 * @property \yii\db\ActiveQueryInterface $discount
 * @property int                          $userGroupId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class DiscountUserGroup extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_discount_usergroups}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['discountId', 'userGroupId'], 'unique', 'targetAttribute' => ['discountId', 'userGroupId']],
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['id' => 'discountId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(UserGroup::class, ['id' => 'userGroupId']);
    }
}
