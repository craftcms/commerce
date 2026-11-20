<?php

declare(strict_types=1);

namespace CraftCms\Commerce\License\Validation;

use CraftCms\Cms\Element\Validation\ElementRules;
use Illuminate\Validation\Rule;
use Override;

use function CraftCms\Cms\t;

class LicenseRules extends ElementRules
{
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules();
        $live = $this->inScenarios(self::SCENARIO_LIVE);

        $rules['variantId'] = ['nullable', 'integer', Rule::when($live, ['required'])];
        $rules['orderId'] = ['nullable', 'integer'];
        $rules['userId'] = ['nullable', 'integer', Rule::when($live && empty($this->subject->ownerEmail), ['required'])];
        $rules['ownerEmail'] = ['nullable', 'email', 'max:255'];
        $rules['ownerName'] = ['nullable', 'string', 'max:255'];
        $rules['licenseKey'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    #[Override]
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'userId.required' => t('A license must have either an email or an owner assigned to it.', category: 'commerce'),
        ];
    }
}
