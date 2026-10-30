<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use Override;

class TransferCondition extends ElementCondition
{
    #[Override]
    public ?string $elementType = Transfer::class;
}
