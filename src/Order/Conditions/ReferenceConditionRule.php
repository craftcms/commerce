<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use Override;

use function CraftCms\Cms\t;

class ReferenceConditionRule extends OrderTextValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'reference';

    #[Override]
    public function getLabel(): string
    {
        return t('Reference', category: 'commerce');
    }
}
