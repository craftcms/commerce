<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Commerce\DigitalProduct\DigitalProducts;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\Product\ProductType\ProductTypes;

use function CraftCms\Cms\t;

class GqlSchemaComponentsResolvingListener
{
    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $this->addDigitalProductTypes($event);

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

    /**
     * Adds the digital product type scopes, which keep the Digital Products plugin's names.
     */
    private function addDigitalProductTypes(GqlSchemaComponentsResolving $event): void
    {
        if (!app(DigitalProducts::class)->isEnabled()) {
            return;
        }

        $permissions = [];

        foreach (app(DigitalProductTypes::class)->getAllDigitalProductTypes() as $type) {
            $permissions["digitalProductTypes.$type->uid:read"] = [
                'label' => t('View digital product type - {productType}', ['productType' => t($type->name, category: 'site')], category: 'commerce'),
            ];
        }

        if ($permissions) {
            $event->queries[t('Digital Products', category: 'commerce')] = $permissions;
        }
    }
}
