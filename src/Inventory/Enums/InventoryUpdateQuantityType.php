<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

enum InventoryUpdateQuantityType: string
{
    use EnumHelpersTrait;

    case ADJUST = 'adjust';
    case SET = 'set';
}
