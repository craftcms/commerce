<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\LinkTypes;

use CraftCms\Cms\Field\LinkTypes\BaseElementLinkType;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;

class ProductLinkType extends BaseElementLinkType
{
    #[\Override]
    protected static function elementType(): string
    {
        return Product::class;
    }

    #[\Override]
    protected function availableSourceKeys(): array
    {
        $sources = [];
        $productTypes = app(ProductTypes::class)->getAllProductTypes();
        $sites = Sites::getAllSites();

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
    }
}
