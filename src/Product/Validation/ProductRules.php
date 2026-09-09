<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Validation;

use CraftCms\Cms\Element\Validation\ElementRules;

/**
 * Product's legacy `defineRules()` was mostly a set of imperative validators over the product's
 * in-memory variants (at least one variant, unique SKUs, no temp SKUs, max variants), which add
 * errors to the `variants` attribute rather than validating a value. Those are kept as plain
 * methods on the {@see \CraftCms\Commerce\Product\Elements\Product} element itself and wired up via
 * {@see \CraftCms\Cms\Validation\Ruleset::after()}, which automatically calls
 * `$product->afterValidate($validator)` when it exists. This ruleset therefore only covers the
 * handful of attributes that were plain type rules in the legacy `defineRules()`.
 */
class ProductRules extends ElementRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['typeId'] = ['nullable', 'integer'];
        $rules['postDate'] = ['nullable', 'date'];
        $rules['expiryDate'] = ['nullable', 'date'];

        return $rules;
    }
}
