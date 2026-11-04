<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Commerce\Product\ProductType\ProductTypes;

use function CraftCms\Cms\t;

class GqlSchemaComponentsResolvingListener
{
    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        if (empty($productTypes)) {
            return;
        }

        $label = t('Products', category: 'commerce');
        $productPermissions = [];

        foreach ($productTypes as $productType) {
            $suffix = 'productTypes.' . $productType->uid;
            $productPermissions[$suffix . ':read'] = [
                'label' => t('View product type - {productType}', ['productType' => t($productType->name, category: 'site')], category: 'commerce'),
            ];
        }

        $event->queries[$label] = $productPermissions;
    }
}
