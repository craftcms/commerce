<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets\Concerns;

trait HasCommerceIcon
{
    #[\Override]
    public static function icon(): ?string
    {
        return dirname(__DIR__, 4) . '/src-yii2/icon-mask.svg';
    }
}
