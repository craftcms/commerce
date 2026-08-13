<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_discounts` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Promotion\Discounts} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Promotion\Models\Discount}
 * object that the rest of the codebase actually works with.
 */
class Discount extends BaseModel
{
    public const string TYPE_ORIGINAL_SALEPRICE = 'original';

    public const string TYPE_DISCOUNTED_SALEPRICE = 'discounted';

    public const string CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';

    public const string CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';

    public const string APPLIED_TO_MATCHING_LINE_ITEMS = 'matchingLineItems';

    public const string APPLIED_TO_ALL_LINE_ITEMS = 'allLineItems';

    #[\Override]
    protected $table = Table::DISCOUNTS;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'allCategories' => 'boolean',
        'allPurchasables' => 'boolean',
        'categoryIds' => 'array',
        'purchasableIds' => 'array',
        'baseDiscount' => 'float',
        'purchaseTotal' => 'float',
        'dateFrom' => 'datetime',
        'dateTo' => 'datetime',
        'enabled' => 'boolean',
        'excludeOnPromotion' => 'boolean',
        'hasFreeShippingForMatchingItems' => 'boolean',
        'hasFreeShippingForOrder' => 'boolean',
        'maxPurchaseQty' => 'integer',
        'percentDiscount' => 'float',
        'perEmailLimit' => 'integer',
        'perItemDiscount' => 'float',
        'perUserLimit' => 'integer',
        'purchaseQty' => 'integer',
        'orderCondition' => 'array',
        'customerCondition' => 'array',
        'shippingAddressCondition' => 'array',
        'billingAddressCondition' => 'array',
        'requireCouponCode' => 'boolean',
        'sortOrder' => 'integer',
        'stopProcessing' => 'boolean',
        'ignorePromotions' => 'boolean',
        'totalDiscountUseLimit' => 'integer',
        'totalDiscountUses' => 'integer',
    ];
}
