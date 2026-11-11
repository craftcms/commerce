<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct;

use CraftCms\Cms\Support\Typecast;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\DigitalProduct\Queries\DigitalProductQuery;
use CraftCms\Commerce\DigitalProduct\Queries\LicenseQuery;

/**
 * `craft.digitalProducts`, matching the Digital Products plugin’s template variable.
 */
class DigitalProductsVariable
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function products(array $criteria = []): DigitalProductQuery
    {
        $query = DigitalProduct::find();
        Typecast::configure($query, $criteria);

        return $query;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function licenses(array $criteria = []): LicenseQuery
    {
        $query = License::find();
        Typecast::configure($query, $criteria);

        return $query;
    }

    /**
     * @return DigitalProductType[]
     */
    public function getProductTypes(): array
    {
        return app(DigitalProductTypes::class)->getAllDigitalProductTypes();
    }
}
