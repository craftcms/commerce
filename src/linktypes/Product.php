<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\linktypes;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\Plugin;
use craft\fields\linktypes\BaseElementLinkType;

/**
 * Product link type.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.1.0
 */
class Product extends BaseElementLinkType
{
    protected static function elementType(): string
    {
        return ProductElement::class;
    }

    protected function availableSourceKeys(): array
    {
        $sources = [];
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($productTypes as $productType) {
            $siteSettings = $productType->getSiteSettings();
            foreach ($sites as $site) {
                if (isset($siteSettings[$site->id]) && $siteSettings[$site->id]->hasUrls) {
                    $sources[] = "productType:$productType->uid";
                    break;
                }
            }
        }

        $sources = array_values(array_unique($sources));

        if (!empty($sources)) {
            array_unshift($sources, '*');
        }

        return $sources;
    }}
