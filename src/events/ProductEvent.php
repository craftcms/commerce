<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Product;
use craft\events\CancelableEvent;

class ProductEvent extends CancelableEvent
{
    // Properties
    // =============================================================================

    /**
     * @var Product The address model
     */
    public $product;

    /**
     * @var bool If this is a new product
     */
    public $isNew;
}
