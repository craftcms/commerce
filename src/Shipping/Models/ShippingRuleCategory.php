<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;

class ShippingRuleCategory extends Component
{
    public ?int $id = null;

    public int $shippingRuleId;

    public int $shippingCategoryId;

    public ?float $perItemRate = null;

    public ?float $weightRate = null;

    public ?float $percentageRate = null;

    public string $condition;

    #[\Override]
    public function getRules(): array
    {
        return [
            'condition' => ['required', 'in:allow,disallow,require'],
            'perItemRate' => ['nullable', 'numeric'],
            'weightRate' => ['nullable', 'numeric'],
            'percentageRate' => ['nullable', 'numeric'],
        ];
    }

    public function getRule(): \craft\commerce\models\ShippingRule
    {
        return Plugin::getInstance()->getShippingRules()->getShippingRuleById($this->shippingRuleId);
    }

    public function getCategory(): ShippingCategory
    {
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }
}
