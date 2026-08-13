<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_taxrates` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Tax\TaxRates}
 * to read/write rows, which are then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Tax\Models\TaxRate} object that the rest of the codebase actually
 * works with.
 */
class TaxRate extends BaseModel
{
    /**
     * Tax subject is line item price.
     */
    public const string TAXABLE_PURCHASABLE = 'purchasable';

    /**
     * Tax subject is line item price.
     */
    public const string TAXABLE_PRICE = 'price';

    /**
     * Tax subject is line item shipping cost.
     */
    public const string TAXABLE_SHIPPING = 'shipping';

    /**
     * Tax subject is line item price and shipping cost.
     */
    public const string TAXABLE_PRICE_SHIPPING = 'price_shipping';

    /**
     * Tax subject is order total shipping cost.
     */
    public const string TAXABLE_ORDER_TOTAL_SHIPPING = 'order_total_shipping';

    /**
     * Tax subject is order total price.
     */
    public const string TAXABLE_ORDER_TOTAL_PRICE = 'order_total_price';

    /**
     * Order-specific tax subject options.
     */
    public const array ORDER_TAXABALES = [
        self::TAXABLE_ORDER_TOTAL_PRICE,
        self::TAXABLE_ORDER_TOTAL_SHIPPING,
    ];

    #[\Override]
    protected $table = Table::TAXRATES;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'taxCategoryId' => 'integer',
        'taxZoneId' => 'integer',
        'rate' => 'float',
        'include' => 'boolean',
        'isVat' => 'boolean',
        'removeIncluded' => 'boolean',
        'removeVatIncluded' => 'boolean',
        'isEverywhere' => 'boolean',
        'enabled' => 'boolean',
        'taxIdValidators' => 'array',
    ];
}
