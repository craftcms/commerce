<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Catalog\Elements\Product;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Catalog\Products::class)` instead.
 */
class Products extends Component
{
    /**
     * @param array|int|string|null $siteId
     */
    public function getProductById(int $id, array|int|string|null $siteId = null, array $criteria = []): ?Product
    {
        return app(\CraftCms\Commerce\Catalog\Products::class)->getProductById($id, $siteId, $criteria);
    }
}
