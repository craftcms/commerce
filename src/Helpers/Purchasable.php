<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use craft\commerce\Plugin;
use CraftCms\Cms\Cp\Cp;
use CraftCms\Cms\Site\Exceptions\SiteNotFoundException;
use CraftCms\Cms\Support\Str;
use Illuminate\Support\Collection;

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

    /**
     * @throws SiteNotFoundException
     * @throws \InvalidArgumentException
     */
    public static function catalogPricingRulesTableByPurchasableId(int $purchasableId, int $storeId, ?Collection $catalogPricing = null): string
    {
        $catalogPricing ??= Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId, $storeId);
        $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($catalogPricingRules->isEmpty()) {
            return '';
        }

        return Cp::renderTemplate('commerce/prices/_table', [
            'catalogPrices' => $catalogPricing,
            'showPurchasable' => false,
            'removeMargin' => true,
        ]);
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

        return Cp::textHtml($config);
    }

    public static function availableForPurchaseInputHtml(bool $value, array $config = []): string
    {
        $config += [
            'id' => 'available-for-purchase',
            'name' => 'availableForPurchase',
            'small' => true,
            'on' => $value,
        ];

        return Cp::lightswitchHtml($config);
    }
}
