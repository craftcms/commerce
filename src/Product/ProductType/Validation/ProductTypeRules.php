<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Validation;

use CraftCms\Cms\Validation\Rules\HandleRule;
use CraftCms\Cms\Validation\Ruleset;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use Illuminate\Validation\Rule;

/**
 * Legacy `ProductType::defineRules()` validated the field layouts, preview targets, and site
 * settings imperatively (mutating the field layout objects, adding errors under nested attribute
 * keys). Those are kept as plain methods on {@see ProductType} itself, wired up via
 * {@see Ruleset::after()}, which automatically calls `$productType->afterValidate($validator)`
 * when it exists. This ruleset covers only the plain declarative rules from the legacy
 * `defineRules()`.
 *
 * @extends Ruleset<ProductType>
 */
class ProductTypeRules extends Ruleset
{
    public function rules(): array
    {
        $rules = [
            'id' => ['nullable', 'integer'],
            'fieldLayoutId' => ['nullable', 'integer'],
            'variantFieldLayoutId' => ['nullable', 'integer'],
            'structureId' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required',
                'string',
                'max:255',
                new HandleRule(['id', 'dateCreated', 'dateUpdated', 'uid', 'title']),
            ],
            'descriptionFormat' => ['nullable', 'string', 'max:255'],
            'maxVariants' => ['nullable', 'integer', 'min:1'],
            'variantTitleFormat' => [
                Rule::requiredIf(fn() => !$this->subject->hasVariantTitleField),
            ],
            'productTitleFormat' => [
                Rule::requiredIf(fn() => !$this->subject->hasProductTitleField),
            ],
        ];

        if ($this->subject->validateHandleUniqueness) {
            $rules['handle'][] = Rule::unique(Table::PRODUCTTYPES, 'handle')->ignore($this->subject->id);
        }

        return $rules;
    }
}
