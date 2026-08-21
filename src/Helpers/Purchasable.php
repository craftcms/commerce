<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Site\Exceptions\SiteNotFoundException;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use Illuminate\Support\Collection;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

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
        $catalogPricing ??= app(CatalogPricing::class)->getCatalogPricesByPurchasableId($purchasableId, $storeId);
        $catalogPricingRules = app(CatalogPricingRules::class)->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);

        if ($catalogPricingRules->isEmpty()) {
            return '';
        }

        return template('commerce/prices/_table', [
            'catalogPrices' => $catalogPricing,
            'showPurchasable' => false,
            'removeMargin' => true,
        ], templateMode: TemplateMode::Cp);
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
