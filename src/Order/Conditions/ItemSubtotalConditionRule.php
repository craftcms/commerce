<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use Override;

use function CraftCms\Cms\t;

class ItemSubtotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'itemSubtotal';

    #[Override]
    public function getLabel(): string
    {
        return t('Item Subtotal', category: 'commerce');
    }
}
