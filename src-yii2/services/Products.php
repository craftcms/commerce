<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Product\Elements\Product;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Product\Products::class)` instead.
 */
class Products extends Component
{
    /**
     * @param array|int|string|null $siteId
     */
    public function getProductById(int $id, array|int|string|null $siteId = null, array $criteria = []): ?Product
    {
        return app(\CraftCms\Commerce\Product\Products::class)->getProductById($id, $siteId, $criteria);
    }
}
