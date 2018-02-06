<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Sale record.
 *
 * @property int           $id
 * @property string        $name
 * @property string        $description
 * @property \DateTime     $dateFrom
 * @property \DateTime     $dateTo
 * @property string        $discountType
 * @property float         $discountAmount
 * @property bool          $allGroups
 * @property bool          $allPurchasables
 * @property bool          $allCategories
 * @property bool          $enabled
 * @property Product[]     $products
 * @property ProductType[] $productTypes
 * @property UserGroup[]   $groups
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Sale extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TYPE_BY_PERCENT = 'byPercent';
    const TYPE_BY_FLAT = 'byFlat';
    const TYPE_TO_PERCENT = 'toPercent';
    const TYPE_TO_FLAT = 'toFlat';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_sales}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['discountType'], 'required']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'userGroupId'])->viaTable('{{%commerce_sale_usergroup}}', ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(Purchasable::class, ['id' => 'purchasableId'])->viaTable('{{%commerce_sale_purchasables}}', ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCategories(): ActiveQueryInterface
    {
        return $this->hasMany(Category::class, ['id' => 'categoryId'])->viaTable('{{%commerce_sale_categories}}', ['saleId' => 'id']);
    }
}
