<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\LineItem\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

use function CraftCms\Cms\t;

enum LineItemType: string
{
    use EnumHelpersTrait;

    case Custom = 'custom';
    case Purchasable = 'purchasable';

    public static function types(): array
    {
        return array_combine(self::names(), self::cases());
    }

    public function typeAsLabel(): string
    {
        return match ($this) {
            self::Custom => t('Custom', category: 'commerce'),
            self::Purchasable => t('Purchasable', category: 'commerce'),
        };
    }
}
