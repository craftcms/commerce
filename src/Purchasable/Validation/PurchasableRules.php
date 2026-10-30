<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Validation;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Validation\Rules\UniqueCaseInsensitiveRule;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchasableRules extends ElementRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['sku'] = [
            'string',
            'max:255',
            Rule::when($this->inScenarios(self::SCENARIO_LIVE), [
                'required',
                new UniqueCaseInsensitiveRule(Table::PURCHASABLES, 'sku')
                    ->where(fn($query) => $query->whereIn('id', DB::table(CraftTable::ELEMENTS)->whereNull('revisionId')->whereNull('draftId')->select('id')))
                    ->ignore($this->subject->id),
            ]),
        ];
        $rules['price'] = ['nullable', 'numeric', Rule::when($this->inScenarios(self::SCENARIO_LIVE), ['required'])];
        $rules['promotionalPrice'] = ['nullable', 'numeric'];
        $rules['weight'] = ['nullable', 'numeric'];
        $rules['width'] = ['nullable', 'numeric'];
        $rules['length'] = ['nullable', 'numeric'];
        $rules['height'] = ['nullable', 'numeric'];
        $rules['basePrice'] = ['nullable', 'numeric'];
        $rules['basePromotionalPrice'] = ['nullable', 'numeric'];
        $rules['minQty'] = ['nullable', 'numeric'];
        $rules['maxQty'] = ['nullable', 'numeric'];
        $rules['freeShipping'] = ['boolean'];
        $rules['inventoryTracked'] = ['boolean'];
        $rules['allowOutOfStockPurchases'] = ['boolean'];
        $rules['promotable'] = ['boolean'];
        $rules['availableForPurchase'] = ['boolean'];
        $rules['taxCategoryId'] = ['required', 'integer'];
        $rules['shippingCategoryId'] = ['required', 'integer'];

        return $rules;
    }
}
