<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\LineItem\Validation;

use CraftCms\Cms\Validation\Ruleset;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use Illuminate\Validation\Rule;

/**
 * Purchasable-supplied checks (stock, qty limits, donation amount, etc. — see
 * {@see \CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface::validateLineItem()}) are kept
 * imperative on {@see LineItem::afterValidate()} rather than folded in here, since they need to
 * resolve and delegate to the purchasable itself. This ruleset covers only the plain declarative
 * rules from the legacy `getValidationRules()`.
 *
 * @extends Ruleset<LineItem>
 */
class LineItemRules extends Ruleset
{
    public function rules(): array
    {
        return [
            'optionsSignature' => ['required'],
            'price' => ['required', 'numeric', 'min:0'],
            'promotionalPrice' => ['nullable', 'numeric', 'min:0'],
            'promotionalAmount' => ['required'],
            'weight' => ['required'],
            'length' => ['required'],
            'height' => ['required'],
            'width' => ['required'],
            'qty' => ['required', 'integer', 'min:1'],
            'taxCategoryId' => ['required', 'integer'],
            'shippingCategoryId' => ['required', 'integer'],
            'type' => ['required'],
            'snapshot' => Rule::requiredIf(fn() => $this->subject->type === LineItemType::Purchasable),
        ];
    }
}
