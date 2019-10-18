<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Sale user group record.
 *
 * @property int $id
 * @property ActiveQueryInterface $sale
 * @property int $saleId
 * @property ActiveQueryInterface $userGroup
 * @property int $userGroupId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SaleUserGroup extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SALE_USERGROUPS;
    }

    /**
     * @inheritdoc
     */
    public function rules()
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
