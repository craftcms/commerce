<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

use function CraftCms\Cms\t;

enum ContainsPurchasablesMatch: string
{
    use EnumHelpersTrait;

    case Any = 'any';
    case All = 'all';
    case Only = 'only';

    public function label(): string
    {
        return match ($this) {
            self::Any => t('any', category: 'commerce'),
            self::All => t('all', category: 'commerce'),
            self::Only => t('only', category: 'commerce'),
        };
    }
}
