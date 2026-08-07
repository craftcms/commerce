<?php

namespace craft\commerce\services;

use craft\commerce\elements\Product;
use craft\events\SiteEvent;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Products::class)` instead.
 */
class Products extends Component
{
    /**
     * @param array|int|string|null $siteId
     */
    public function getProductById(int $id, array|int|string|null $siteId = null, array $criteria = []): ?Product
    {
        return app(\CraftCms\Commerce\Services\Products::class)->getProductById($id, $siteId, $criteria);
    }

    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Products::class)->afterSaveSiteHandler($event);
    }
}
