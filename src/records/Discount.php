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
 * @property ?array $categoryIds
 * @property bool $allPurchasables
 * @property ?array $purchasableIds
 * @property float $baseDiscount
 * @property float $purchaseTotal
 * @property string $baseDiscountType
 * @property string $couponFormat
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
 * @property int $perEmailLimit
 * @property float $perItemDiscount
 * @property int $perUserLimit
 * @property int $purchaseQty
 * @property array|null $orderCondition
 * @property array|null $customerCondition
 * @property array|null $shippingAddressCondition
 * @property array|null $billingAddressCondition
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
    public const TYPE_ORIGINAL_SALEPRICE = 'original';
    public const TYPE_DISCOUNTED_SALEPRICE = 'discounted';

    public const BASE_DISCOUNT_TYPE_VALUE = 'value';
    public const BASE_DISCOUNT_TYPE_PERCENT_TOTAL = 'percentTotal';
    public const BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED = 'percentTotalDiscounted';
    public const BASE_DISCOUNT_TYPE_PERCENT_ITEMS = 'percentItems';
    public const BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED = 'percentItemsDiscounted';

    public const CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';
    public const CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';
    public const CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    public const APPLIED_TO_MATCHING_LINE_ITEMS = 'matchingLineItems';
    public const APPLIED_TO_ALL_LINE_ITEMS = 'allLineItems';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::DISCOUNTS;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getDiscountPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountPurchasable::class, ['discountId' => 'id']);
    }

    public function getDiscountCategories(): ActiveQueryInterface
    {
        return $this->hasMany(DiscountCategory::class, ['discountId' => 'id']);
    }

    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'discountId'])->via('discountUserGroups');
    }

    public function getPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(Purchasable::class, ['id' => 'discountId'])->via('discountPurchasables');
    }

    public function getCategories(): ActiveQueryInterface
    {
        return $this->hasMany(Category::class, ['id' => 'discountId'])->via('discountCategories');
    }
}
