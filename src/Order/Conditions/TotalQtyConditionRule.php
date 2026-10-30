<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use Override;

use function CraftCms\Cms\t;

class TotalQtyConditionRule extends OrderValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'totalQty';

    #[Override]
    public function getLabel(): string
    {
        return t('Total Qty', category: 'commerce');
    }
}
