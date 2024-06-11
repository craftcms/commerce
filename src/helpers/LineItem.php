<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\Plugin;
use craft\helpers\Json;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Line item helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class LineItem
{
    /**
     * @return string The generated options signature
     */
    public static function generateOptionsSignature(array $options = [], ?int $lineItemId = null): string
    {
        if ($lineItemId) {
            $options['lineItemId'] = $lineItemId;
        }
        ksort($options);
        return md5(Json::encode($options));
    }

    /**
     * @param string $description
     * @param string $sku
     * @param float $price
     * @return string
     * @throws Exception
     * @throws InvalidConfigException
     * @since 5.1.0
     */
    public static function generateCustomLineItemHash(string $description, string $sku, float $price, ?int $shippingCategoryId = null, ?int $taxCategoryId = null, bool $hasFreeShipping = false, bool $isPromotable = false, bool $isShippable = false, bool $isTaxable = false): string
    {
        $customLineItem = compact('description', 'price', 'sku');
        $customLineItem = [
            'description' => $description,
            'price' => $price,
            'sku' => $sku,
            'hasFreeShipping' => $hasFreeShipping,
            'isPromotable' => $isPromotable,
            'isShippable' => $isShippable,
            'isTaxable' => $isTaxable,
        ];

        if ($shippingCategoryId === null) {
            $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();
            $customLineItem['shippingCategoryId'] = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($currentStore->id)->id;
        }

        if ($taxCategoryId === null) {
            $customLineItem['taxCategoryId'] = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
        }

        ksort($customLineItem);

        return Craft::$app->getSecurity()->hashData(Json::encode($customLineItem));
    }
}
