<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Sale user group record.
 *
 * @property int                          $id
 * @property int                          $saleId
 * @property \yii\db\ActiveQueryInterface $userGroup
 * @property \yii\db\ActiveQueryInterface $sale
 * @property int                          $userGroupId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class SaleUserGroup extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_sale_usergroups}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['saleId', 'userGroupId'], 'unique', 'targetAttribute' => ['saleId', 'userGroupId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getSale(): ActiveQueryInterface
    {
        return $this->hasOne(Sale::class, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getUserGroup(): ActiveQueryInterface
    {
        return $this->hasOne(UserGroup::class, ['saleId' => 'id']);
    }
}
