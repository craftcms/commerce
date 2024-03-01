<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\Plugin;
use craft\errors\SiteNotFoundException;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;

/**
 * Purchasable helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.8
 */
class Purchasable
{
    public const TEMPORARY_SKU_PREFIX = '__temp_';

    /**
     * Generates a new temporary SKU.
     *
     * @since 3.2.8
     */
    public static function tempSku(): string
    {
        return static::TEMPORARY_SKU_PREFIX . StringHelper::randomString();
    }

    /**
     * Returns whether the given SKU is temporary.
     *
     * @since 3.2.8
     */
    public static function isTempSku(string $sku): bool
    {
        return str_starts_with($sku, static::TEMPORARY_SKU_PREFIX);
    }

    /**
     * @param int $purchasableId
     * @param int $storeId
     * @param Collection|null $catalogPricing
     * @return string
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public static function catalogPricingRulesTableByPurchasableId(int $purchasableId, int $storeId, ?Collection $catalogPricing = null): string
    {
        $catalogPricing = $catalogPricing ?? Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);
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

    /**
     * @param string|null $value
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function skuInputHtml(?string $value = null, array $config = []): string
    {
        $config += [
            'id' => 'sku',
            'name' => 'sku',
            'value' => $value,
            'placeholder' => Craft::t('commerce', 'Enter SKU'),
            'class' => 'code',
        ];

        return Cp::textHtml($config);
    }

    /**
     * @param bool $value
     * @param array $config
     * @return string
     * @since 5.0.0
     */
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
