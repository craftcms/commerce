<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Discount record.
 *
 * @property bool $allCategories
 * @property bool $allGroups
 * @property bool $allPurchasables
 * @property float $baseDiscount
 * @property string $code
 * @property \DateTime $dateFrom
 * @property \DateTime $dateTo
 * @property string $description
 * @property ActiveQueryInterface $discountUserGroups
 * @property bool $enabled
 * @property bool $excludeOnSale
 * @property bool $hasFreeShippingForMatchingItems
 * @property bool $hasFreeShippingForOrder
 * @property UserGroup[] $groups
 * @property int $id
 * @property int $maxPurchaseQty
 * @property string $name
 * @property string $percentageOffSubject
 * @property float $percentDiscount
 * @property int $perEmailLimit
 * @property float $perItemDiscount
 * @property int $perUserLimit
 * @property int $purchaseQty
 * @property int $purchaseTotal
 * @property int $sortOrder
 * @property bool $stopProcessing
 * @property int $totalUseLimit
 * @property int $totalUses
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
