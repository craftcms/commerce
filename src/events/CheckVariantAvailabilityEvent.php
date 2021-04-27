<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\events\CancelableEvent;

/**
 * Class CheckVariantAvailabilityEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class CheckVariantAvailabilityEvent extends CancelableEvent
{
    /**
     * @var Product The product model associated with the event.
     */
    public $product;

    /**
     * @var Variant The variant model associated with the event.
     */
    public $variant;
}
