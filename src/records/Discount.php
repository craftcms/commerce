<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Discount record.
 *
 * @property bool $allCategories
 * @property bool $allGroups
 * @property bool $allPurchasables
 * @property float $baseDiscount
 * @property string $baseDiscountType
 * @property string $code
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
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
 * @property string $appliedTo
 * @property string $perEmailLimit
 * @property float $perItemDiscount
 * @property int $perUserLimit
 * @property int $purchaseQty
 * @property int $purchaseTotal
 * @property string|null $orderConditionFormula
 * @property int $sortOrder
 * @property bool $stopProcessing
 * @property bool $ignoreSales
 * @property int $totalDiscountUseLimit
 * @property int $totalDiscountUses
 * @property string $categoryRelationshipType
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Discount extends ActiveRecord
{
    const TYPE_ORIGINAL_SALEPRICE = 'original';
    const TYPE_DISCOUNTED_SALEPRICE = 'discounted';

    const BASE_DISCOUNT_TYPE_VALUE = 'value';
    const BASE_DISCOUNT_TYPE_PERCENT_TOTAL = 'percentTotal';
    const BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED = 'percentTotalDiscounted';
    const BASE_DISCOUNT_TYPE_PERCENT_ITEMS = 'percentItems';
    const BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED = 'percentItemsDiscounted';

    const CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';
    const CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';
    const CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    const APPLIED_TO_MATCHING_LINE_ITEMS = 'matchingLineItems';
    const APPLIED_TO_ALL_LINE_ITEMS = 'allLineItems';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::DISCOUNTS;
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
