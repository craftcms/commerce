<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use Override;

use function CraftCms\Cms\t;

class TotalPriceConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'totalPrice';

    #[Override]
    public function getLabel(): string
    {
        return t('Total Price', category: 'commerce');
    }
}
