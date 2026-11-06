<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\errors\SiteNotFoundException;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets as AssetsField;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use Throwable;
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
     * Returns the asset a purchasable’s field layout uses as its thumbnail, if any.
     *
     * That’s the asset in the layout’s “Thumbnail Source”, which has to be an Assets field. A variant without one
     * falls back to its product’s.
     *
     * @since 5.8.0
     */
    public static function thumbnailAsset(ElementInterface $element): ?Asset
    {
        $fieldLayout = $element->getFieldLayout();
        $layoutElement = null;

        if ($fieldLayout?->thumbFieldKey && str_starts_with($fieldLayout->thumbFieldKey, 'layoutElement:')) {
            $layoutElement = $fieldLayout->getElementByKey($fieldLayout->thumbFieldKey);
        }

        if ($layoutElement instanceof CustomField && $layoutElement->getField() instanceof AssetsField) {
            $value = $element->getFieldValue($layoutElement->attribute());
            $asset = $value instanceof AssetQuery ? $value->one() : null;

            if ($asset instanceof Asset && $asset->kind === Asset::KIND_IMAGE) {
                return $asset;
            }
        }

        if ($element instanceof Variant && $product = $element->getProduct()) {
            return static::thumbnailAsset($product);
        }

        return null;
    }

    /**
     * Returns the URL of a square thumbnail of a purchasable’s thumbnail source, or `null` if it doesn’t have one.
     *
     * @param int $size The width and height, in pixels
     * @since 5.8.0
     */
    public static function thumbnailUrl(ElementInterface $element, int $size): ?string
    {
        $asset = static::thumbnailAsset($element);

        if (!$asset) {
            return null;
        }

        try {
            return $asset->getUrl(['width' => $size, 'height' => $size, 'mode' => 'crop']);
        } catch (Throwable $e) {
            Craft::warning("Couldn’t get a thumbnail URL for asset $asset->id: {$e->getMessage()}", __METHOD__);

            return null;
        }
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
