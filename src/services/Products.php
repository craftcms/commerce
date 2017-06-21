<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\events\ProductEvent;
use craft\commerce\Plugin;
use craft\commerce\records\Product as ProductRecord;
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
    // Constants
    // =========================================================================

    /**
     * @event ProductEvent The event that is raised before a product is saved.
     */
    const EVENT_BEFORE_SAVE_PRODUCT = 'beforeSaveProduct';

    /**
     * @event ProductEvent The event that is raised after a product is saved.
     */
    const EVENT_AFTER_SAVE_PRODUCT = 'afterSaveProduct';

    /**
     * @event ProductEvent This event is raised when a new product is created from a purchasable
     *
     * You may set [[ProductEvent::isValid]] to `false` to prevent the product from being deleted.
     */
    const EVENT_BEFORE_DELETE_PRODUCT = 'beforeDeleteProduct';

    /**
     * @event ProductEvent This event is raised when a new product is populated from a purchasable

     */
    const EVENT_AFTER_DELETE_PRODUCT = 'afterDeleteProduct';

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
