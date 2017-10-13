<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use yii\base\Component;

/**
 * Product service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Products extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     * @param int $localeId
     *
     * @return Product|null
     */
    public function getProductById(int $id, $localeId = null)
    {
        /** @var Product $product */
        $product = Craft::$app->getElements()->getElementById($id, Product::class, $localeId);

        return $product;
    }
}
