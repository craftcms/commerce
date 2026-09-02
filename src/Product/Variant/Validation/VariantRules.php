<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Validation;

use CraftCms\Commerce\Purchasable\Validation\PurchasableRules;

/**
 * Variant's declarative rules on top of {@see PurchasableRules} (which already covers `sku`,
 * `price`, dimensions, `minQty`/`maxQty` types, etc.).
 *
 * The legacy `defineRules()` also declared three custom validators — `validatePrice`,
 * `validateMinQtyRange` and `validateMaxQtyRange`. Those are kept as plain methods on the
 * {@see \CraftCms\Commerce\Product\Variant\Elements\Variant} element and wired up via
 * {@see \CraftCms\Cms\Validation\Ruleset::after()}, which automatically calls
 * `$variant->afterValidate($validator)` when it exists.
 */
class VariantRules extends PurchasableRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['stock'] = ['nullable', 'integer'];
        $rules['fieldId'] = ['nullable', 'integer'];
        $rules['ownerId'] = ['nullable', 'integer'];
        $rules['primaryOwnerId'] = ['nullable', 'integer'];
        $rules['sortOrder'] = ['nullable', 'integer'];

        return $rules;
    }
}
