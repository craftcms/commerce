<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Support\Str;

use function CraftCms\Cms\t;

class Purchasable
{
    public const TEMPORARY_SKU_PREFIX = '__temp_';

    public static function tempSku(): string
    {
        return static::TEMPORARY_SKU_PREFIX . Str::random();
    }

    public static function isTempSku(string $sku): bool
    {
        return str_starts_with($sku, (string)static::TEMPORARY_SKU_PREFIX);
    }

    public static function skuInputHtml(?string $value = null, array $config = []): string
    {
        $config += [
            'id' => 'sku',
            'name' => 'sku',
            'value' => $value,
            'placeholder' => t('Enter SKU', category: 'commerce'),
            'class' => 'code',
        ];

        return FormFields::textHtml($config);
    }

    public static function availableForPurchaseInputHtml(bool $value, array $config = []): string
    {
        $config += [
            'id' => 'available-for-purchase',
            'name' => 'availableForPurchase',
            'small' => true,
            'on' => $value,
        ];

        return FormFields::lightswitchFromConfig($config)->toHtml();
    }
}
