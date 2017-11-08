<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use yii\base\Component;

/**
 * Product service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Products extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get a product by ID.
     *
     * @param int $id
     * @param int $siteId
     *
     * @return Product|null
     */
    public function getProductById(int $id, $siteId = null)
    {
        /** @var Product $product */
        $product = Craft::$app->getElements()->getElementById($id, Product::class, $siteId);

        return $product;
    }
}
