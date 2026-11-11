<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductTypeSite;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use RuntimeException;

/**
 * Creates digital product types and digital products for tests.
 */
class DigitalProductsFixture
{
    public static function type(string $handle = 'ebooks', ?string $skuFormat = 'EBOOK-{slug}'): DigitalProductType
    {
        $siteId = Sites::getPrimarySite()->id;

        $type = new DigitalProductType([
            'name' => ucfirst($handle),
            'handle' => $handle,
            'skuFormat' => $skuFormat,
        ]);
        $type->setSiteSettings([
            $siteId => new DigitalProductTypeSite([
                'siteId' => $siteId,
                'hasUrls' => true,
                'uriFormat' => "$handle/{slug}",
                'template' => "$handle/_product",
            ]),
        ]);

        if (!app(DigitalProductTypes::class)->saveDigitalProductType($type)) {
            throw new RuntimeException('Could not save digital product type: ' . json_encode($type->errors()->all()));
        }

        return app(DigitalProductTypes::class)->getDigitalProductTypeByHandle($handle);
    }

    public static function product(DigitalProductType $type, string $title = 'Field Guide', float $price = 15, ?string $sku = null): DigitalProduct
    {
        $product = new DigitalProduct();
        $product->typeId = $type->id;
        $product->title = $title;
        $product->siteId = Sites::getPrimarySite()->id;
        $product->setBasePrice($price);
        $product->setSku($sku);

        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save digital product: ' . json_encode($product->errors()->all()));
        }

        return DigitalProduct::find()->id($product->id)->one();
    }
}
