<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Discount record.
 *
 * @property int                  $id
 * @property string               $name
 * @property string               $description
 * @property string               $code
 * @property int                  $perUserLimit
 * @property int                  $perEmailLimit
 * @property int                  $totalUseLimit
 * @property int                  $totalUses
 * @property \DateTime            $dateFrom
 * @property \DateTime            $dateTo
 * @property int                  $purchaseTotal
 * @property int                  $purchaseQty
 * @property int                  $maxPurchaseQty
 * @property float                $baseDiscount
 * @property float                $perItemDiscount
 * @property float                $percentDiscount
 * @property float                $percentageOffSubject
 * @property bool                 $excludeOnSale
 * @property bool                 $freeShipping
 * @property bool                 $allGroups
 * @property bool                 $allPurchasables
 * @property bool                 $allCategories
 * @property bool                 $enabled
 * @property bool                 $stopProcessing
 * @property bool                 $sortOrder
 * @property Product[]            $products
 * @property ProductType[]        $productTypes
 * @property ActiveQueryInterface $discountProducts
 * @property ActiveQueryInterface $discountUserGroups
 * @property ActiveQueryInterface $discountProductTypes
 * @property UserGroup[]          $groups
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Discount extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TYPE_ORIGINAL_SALEPRICE = 'original';
    const TYPE_DISCOUNTED_SALEPRICE = 'discounted';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_discounts}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'required']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountUserGroups(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountUserGroup::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountPurchasable::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscountCategories(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountCategory::class, ['discountId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'discountId'])->via('discountUserGroups');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(Purchasable::class, ['id' => 'discountId'])->via('discountPurchasables');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCategories(): ActiveQueryInterface
    {
        return $this->hasMany(Category::class, ['id' => 'discountId'])->via('discountCategories');
    }
}
