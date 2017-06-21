<?php

namespace craft\commerce\events;

use craft\commerce\models\ProductType;
use yii\base\Event;

/**
 * Product type event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class ProductTypeEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var ProductType|null The product type model associated with the event.
     */
    public $productType;

    /**
     * @var bool Whether the category group is brand new
     */
    public $isNew = false;
}