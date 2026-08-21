<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

enum OrderNoticeType: string
{
    use EnumHelpersTrait;

    case Customer = 'customer';

    case Admin = 'admin';
}
