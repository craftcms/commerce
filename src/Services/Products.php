<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use CraftCms\Commerce\Catalog\Elements\Product;
use craft\commerce\Plugin;
use craft\events\SiteEvent;
use CraftCms\Cms\Element\Jobs\PropagateElements;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Commerce\Database\Table;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

#[Singleton]
class Products
{
    /**
     * Returns a product by its ID.
     *
     * @param array|int|string|null $siteId
     */
    public function getProductById(int $id, array|int|string|null $siteId = null, array $criteria = []): ?Product
    {
        if (!$id) {
            return null;
        }

        // Get the structure ID
        if (!isset($criteria['structureId'])) {
            $criteria['structureId'] = DB::table(Table::PRODUCTS . ' as products')
                ->join(Table::PRODUCTTYPES . ' as productTypes', 'productTypes.id', '=', 'products.typeId')
                ->where('products.id', $id)
                ->value('productTypes.structureId');
        }

        return Elements::getElementById($id, Product::class, $siteId, $criteria);
    }

    /**
     * Handle a Site being saved.
     */
    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        if (
            $event->isNew &&
            isset($event->oldPrimarySiteId) &&
            Plugins::isPluginInstalled(Plugin::getInstance()->handle)
        ) {
            dispatch(new PropagateElements(
                elementType: Product::class,
                criteria: [
                    'siteId' => $event->oldPrimarySiteId,
                    'status' => null,
                ],
                siteId: $event->site->id,
                isNewSite: true,
            ));
        }
    }
}
