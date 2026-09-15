<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Validation;

use Illuminate\Validation\Rule;
use Override;

class DonationRules extends PurchasableRules
{
    #[Override]
    public function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->subject->sku)) {
            $this->subject->sku = trim($this->subject->sku);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['sku'][] = Rule::when(
            fn() => $this->subject->availableForPurchase && $this->subject->enabled,
            ['required'],
        );

        return $rules;
    }
}
