<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Validation;

use CraftCms\Commerce\Purchasable\Validation\PurchasableRules;
use Override;

class DigitalProductRules extends PurchasableRules
{
    #[Override]
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['typeId'] = ['required', 'integer'];
        $rules['postDate'] = ['nullable', 'date'];
        $rules['expiryDate'] = ['nullable', 'date'];

        return $rules;
    }
}
