<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Validation;

use CraftCms\Cms\Validation\Rules\HandleRule;
use CraftCms\Cms\Validation\Ruleset;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use Illuminate\Validation\Rule;

/**
 * The field layout and site settings are validated by {@see DigitalProductType::afterValidate()}.
 *
 * @extends Ruleset<DigitalProductType>
 */
class DigitalProductTypeRules extends Ruleset
{
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'fieldLayoutId' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required',
                'string',
                'max:255',
                new HandleRule(['id', 'dateCreated', 'dateUpdated', 'uid', 'title']),
                Rule::unique(Table::DIGITALPRODUCTTYPES, 'handle')->ignore($this->subject->id),
            ],
            'skuFormat' => ['nullable', 'string', 'max:255'],
        ];
    }
}
