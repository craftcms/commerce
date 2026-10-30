<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_producttypes` table.
 *
 * This holds no business logic — it's written to from
 * {@see \CraftCms\Commerce\Product\ProductType\ProductTypes::handleChangedProductType()} and read back by
 * the same service.
 */
class ProductType extends BaseModel
{
    #[\Override]
    protected $table = Table::PRODUCTTYPES;

    public $timestamps = false;

    #[\Override]
    protected $casts = [
        'isStructure' => 'boolean',
        'maxLevels' => 'integer',
        'structureId' => 'integer',
        'fieldLayoutId' => 'integer',
        'variantFieldLayoutId' => 'integer',
        'enableVersioning' => 'boolean',
        'maxVariants' => 'integer',
        'hasDimensions' => 'boolean',
        'hasVariantTitleField' => 'boolean',
        'hasProductTitleField' => 'boolean',
        'showSlugField' => 'boolean',
        'previewTargets' => 'array',
    ];
}
